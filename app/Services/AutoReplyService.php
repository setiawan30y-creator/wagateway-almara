<?php

namespace App\Services;

use App\Models\AutoReply;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Template;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan auto-reply untuk pesan masuk (maks. satu balasan per pesan).
 * Urutan: kata kunci (prioritas terkecil dulu) → sambutan kontak baru → di luar jam kerja.
 * @2026-Alifiandra
 */
class AutoReplyService
{
    public function __construct(private MessageService $messages) {}

    public function handle(Device $device, Contact $contact, Message $incoming, bool $isNewContact): ?AutoReply
    {
        if ($contact->consent === 'opted_out') {
            return null;
        }

        $rules = AutoReply::where('is_active', true)
            ->where(fn ($q) => $q->whereNull('device_id')->orWhere('device_id', $device->id))
            ->orderBy('priority')->orderBy('id')
            ->get();

        $rule = $rules->where('trigger', 'keyword')->first(fn (AutoReply $r) => $r->matches($incoming->body))
            ?? ($isNewContact ? $rules->firstWhere('trigger', 'welcome') : null)
            ?? (! Setting::inWorkingHours() ? $rules->firstWhere('trigger', 'away') : null);

        if (! $rule || $this->inCooldown($rule, $contact)) {
            return null;
        }

        DB::table('auto_reply_logs')->insert(['auto_reply_id' => $rule->id, 'contact_id' => $contact->id, 'created_at' => now()]);
        $rule->forceFill(['hits_count' => $rule->hits_count + 1, 'last_triggered_at' => now()])->save();

        if ($rule->add_tag_id) {
            $contact->tags()->syncWithoutDetaching([$rule->add_tag_id]);
        }

        $this->messages->queueText($device, $contact->phone, Template::render($rule->reply, $contact), options: ['contact' => $contact, 'source' => 'auto']);

        return $rule;
    }

    private function inCooldown(AutoReply $rule, Contact $contact): bool
    {
        if ($rule->cooldown_minutes <= 0) {
            return false;
        }

        return DB::table('auto_reply_logs')
            ->where('auto_reply_id', $rule->id)
            ->where('contact_id', $contact->id)
            ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
            ->exists();
    }
}
