<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\WaGroup;
use App\Support\PhoneNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sinkron data dari HP (perangkat Scan QR): kontak, riwayat chat, grup.
 * Data riwayat hanya disimpan — tidak memicu auto-reply, AI, notifikasi maupun webhook.
 * @2026-Alifiandra
 */
class WaSyncService
{
    /**
     * Simpan kontak dari HP. Kontak baru: source "wa-sync", izin tetap "belum ada izin".
     * Nama hanya diisi bila kontak belum punya nama (nama buatan tim tidak ditimpa).
     *
     * @param  array<int, array{phone?: string, name?: ?string}>  $list
     * @return array{created: int, updated: int}
     */
    public function importContacts(array $list): array
    {
        $created = 0;
        $updated = 0;

        $rows = collect($list)
            ->map(fn ($c) => ['phone' => PhoneNumber::normalize((string) ($c['phone'] ?? '')), 'name' => trim((string) ($c['name'] ?? '')) ?: null])
            ->filter(fn ($c) => PhoneNumber::isValid($c['phone']))
            ->unique('phone');

        foreach ($rows->chunk(200) as $chunk) {
            $existing = Contact::whereIn('phone', $chunk->pluck('phone'))->get()->keyBy('phone');

            DB::transaction(function () use ($chunk, $existing, &$created, &$updated) {
                foreach ($chunk as $row) {
                    $contact = $existing->get($row['phone']);

                    if (! $contact) {
                        Contact::create(['phone' => $row['phone'], 'name' => $row['name'] ? mb_substr($row['name'], 0, 150) : null, 'source' => 'wa-sync']);
                        $created++;
                    } elseif (! $contact->name && $row['name']) {
                        $contact->forceFill(['name' => mb_substr($row['name'], 0, 150)])->save();
                        $updated++;
                    }
                }
            });
        }

        return compact('created', 'updated');
    }

    /**
     * Simpan riwayat chat terbaru yang dikirim WhatsApp saat perangkat ditautkan.
     *
     * @param  array<int, array>  $items  {id, from, from_me, message, timestamp, name?, group?: {jid, subject}}
     */
    public function importHistory(Device $device, array $items): int
    {
        $items = array_slice($items, 0, 500);
        $ids = array_values(array_filter(array_map(fn ($i) => $i['id'] ?? null, $items)));
        $known = $ids ? Message::where('device_id', $device->id)->whereIn('provider_message_id', $ids)->pluck('provider_message_id')->flip() : collect();

        // urutkan dari yang tertua agar pratinjau percakapan berakhir di pesan terbaru
        usort($items, fn ($a, $b) => ((int) ($a['timestamp'] ?? 0)) <=> ((int) ($b['timestamp'] ?? 0)));

        $saved = 0;

        foreach ($items as $item) {
            $id = isset($item['id']) ? (string) $item['id'] : null;
            $body = trim((string) ($item['message'] ?? ''));

            if (($id && $known->has($id)) || $body === '') {
                continue;
            }

            $at = isset($item['timestamp']) && (int) $item['timestamp'] > 0 ? Carbon::createFromTimestamp((int) $item['timestamp']) : now();
            $fromMe = (bool) ($item['from_me'] ?? false);

            if (! empty($item['group']['jid']) && WaGroup::isJid($item['group']['jid'])) {
                $group = $this->groupFor($device, $item['group']);
                $conversation = Conversation::forGroup($group);
                $attrs = [
                    'group_id' => $group->id,
                    'to' => $group->jid,
                    'participant' => $fromMe ? null : (PhoneNumber::isValid((string) ($item['from'] ?? '')) ? $item['from'] : null),
                    'participant_name' => $fromMe ? null : (isset($item['name']) ? mb_substr((string) $item['name'], 0, 150) : null),
                ];
            } else {
                $phone = PhoneNumber::normalize((string) ($item['from'] ?? ''));

                if (! PhoneNumber::isValid($phone)) {
                    continue;
                }

                $contact = Contact::findOrCreateByPhone($phone, ['name' => ! $fromMe && ! empty($item['name']) ? mb_substr((string) $item['name'], 0, 150) : null, 'source' => 'wa-sync']);
                $conversation = Conversation::forContact($contact, $device);
                $attrs = ['contact_id' => $contact->id, 'to' => $phone];

                if (! $contact->last_message_at || $contact->last_message_at->lt($at)) {
                    $contact->forceFill(['last_message_at' => $at])->save();
                }
            }

            $message = new Message($attrs + [
                'device_id' => $device->id,
                'conversation_id' => $conversation->id,
                'direction' => $fromMe ? 'out' : 'in',
                'body' => mb_substr($body, 0, 10000),
                'status' => $fromMe ? 'sent' : 'received',
                'provider_message_id' => $id,
                'sent_at' => $at,
            ]);
            $message->created_at = $at;
            $message->updated_at = now();
            $message->save();

            $conversation->touchWith($message, countUnread: false);
            $saved++;
        }

        return $saved;
    }

    /**
     * Simpan daftar grup dari engine.
     *
     * @param  array<int, array>  $groups
     */
    public function importGroups(Device $device, array $groups, bool $withParticipants = true): int
    {
        $count = 0;

        foreach ($groups as $g) {
            if (! empty($g['jid']) && WaGroup::isJid((string) $g['jid'])) {
                WaGroup::upsertFromEngine($device, $g, $withParticipants);
                $count++;
            }
        }

        return $count;
    }

    /** Grup dari pesan masuk; dibuat otomatis bila belum pernah disinkron. */
    public function groupFor(Device $device, array $g): WaGroup
    {
        $group = WaGroup::where('device_id', $device->id)->where('jid', $g['jid'])->first();

        if ($group) {
            if (! empty($g['subject']) && $group->subject !== $g['subject']) {
                $group->forceFill(['subject' => mb_substr((string) $g['subject'], 0, 191)])->save();
            }

            return $group;
        }

        return WaGroup::create([
            'device_id' => $device->id,
            'jid' => $g['jid'],
            'subject' => mb_substr((string) (($g['subject'] ?? '') ?: 'Grup '.substr($g['jid'], 0, 12)), 0, 191),
        ]);
    }
}
