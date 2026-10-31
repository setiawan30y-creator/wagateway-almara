<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Tag;
use App\Models\WaGroup;
use App\Services\MediaStorage;
use App\Services\MessageService;
use App\Services\WaSyncService;
use App\WhatsApp\ProviderManager;
use App\WhatsApp\Providers\QrEngineProvider;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Grup WhatsApp (perangkat Scan QR): daftar, sinkron, anggota, kirim pesan, impor anggota ke kontak.
 * Juga sinkron kontak dari HP.
 * @2026-Alifiandra
 */
class GroupController extends Controller
{
    public function __construct(private ProviderManager $providers, private WaSyncService $sync) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $deviceId = $request->integer('device') ?: null;

        return view('groups.index', [
            'groups' => WaGroup::with(['device', 'conversation'])
                ->when($deviceId, fn ($w) => $w->where('device_id', $deviceId))
                ->when($q !== '', fn ($w) => $w->where('subject', 'like', '%'.$q.'%'))
                ->orderBy('subject')
                ->paginate(50)->withQueryString(),
            'devices' => Device::where('provider', 'qr')->orderByDesc('is_default')->get(),
            'deviceId' => $deviceId,
        ]);
    }

    public function show(WaGroup $group)
    {
        $group->load(['device', 'conversation']);
        $phones = collect($group->participants ?? [])->pluck('phone')->filter()->all();

        return view('groups.show', [
            'group' => $group,
            'known' => Contact::whereIn('phone', $phones)->get()->keyBy('phone'),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    /** Ambil daftar grup + anggota terbaru dari HP. */
    public function sync(Request $request)
    {
        $devices = $this->qrDevices($request);
        $total = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                /** @var QrEngineProvider $provider */
                $provider = $this->providers->for($device);
                $total += $this->sync->importGroups($device, $provider->groups($device));
            } catch (RuntimeException $e) {
                $errors[] = $device->name.': '.$e->getMessage();
            }
        }

        AuditLog::record('groups.sync', "Sinkron grup: {$total} grup");

        return back()->with($errors ? 'warning' : 'success', $errors
            ? 'Sebagian gagal — '.implode(' · ', $errors)
            : ($devices->isEmpty() ? 'Belum ada perangkat Scan QR yang terhubung.' : "{$total} grup disinkron."));
    }

    /** Ambil kontak yang tersimpan di HP (lewat engine) lalu simpan ke Kontak. */
    public function syncContacts(Request $request)
    {
        $devices = $this->qrDevices($request);
        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                /** @var QrEngineProvider $provider */
                $provider = $this->providers->for($device);
                $r = $this->sync->importContacts($provider->contacts($device));
                $created += $r['created'];
                $updated += $r['updated'];
            } catch (RuntimeException $e) {
                $errors[] = $device->name.': '.$e->getMessage();
            }
        }

        AuditLog::record('contacts.sync', "Sinkron kontak HP: {$created} baru, {$updated} diperbarui");

        if ($devices->isEmpty()) {
            return back()->with('warning', 'Belum ada perangkat Scan QR yang terhubung.');
        }

        return back()->with($errors ? 'warning' : 'success', trim(
            "Sinkron kontak: {$created} kontak baru, {$updated} nama diperbarui. ".($errors ? 'Gagal — '.implode(' · ', $errors) : '').
            ($created + $updated === 0 && ! $errors ? 'Bila kosong, WhatsApp biasanya mengirim kontak beberapa menit setelah scan QR — coba lagi nanti.' : '')
        ));
    }

    /** Buka chat grup di Inbox. */
    public function chat(WaGroup $group)
    {
        return redirect()->route('inbox.show', Conversation::forGroup($group));
    }

    /** Kirim pesan (teks / lampiran) ke grup. */
    public function send(Request $request, WaGroup $group, MessageService $messages)
    {
        $data = $request->validate([
            'body' => ['required_without:file', 'nullable', 'string', 'max:'.config('whatsapp.max_text_length')],
            'file' => ['nullable', 'file', 'mimes:'.MediaStorage::EXTENSIONS, 'max:'.MediaStorage::MAX_KB],
        ], ['body.required_without' => 'Tulis pesan atau pilih lampiran.'], ['body' => 'pesan', 'file' => 'lampiran']);

        $media = $request->hasFile('file') ? app(MediaStorage::class)->storeUpload($request->file('file')) : null;
        $message = $messages->queueGroup($group, (string) ($data['body'] ?? ''), $request->user(), options: ['media' => $media]);

        return redirect()->route('inbox.show', $message->conversation_id)
            ->with($message->status === 'failed' ? 'warning' : 'success', $message->status === 'failed' ? 'Gagal mengirim: '.$message->error : 'Pesan dikirim ke grup '.$group->subject.'.');
    }

    /** Simpan anggota grup sebagai kontak (izin tetap "belum ada izin"). */
    public function importMembers(Request $request, WaGroup $group)
    {
        $data = $request->validate(['tag' => ['nullable', 'string', 'max:50']]);

        $phones = $group->memberPhones();
        $result = $this->sync->importContacts(array_map(fn ($p) => ['phone' => $p], $phones));

        if (! empty($data['tag'])) {
            $tag = Tag::firstOrCreate(['name' => trim($data['tag'])]);
            $ids = Contact::whereIn('phone', $phones)->pluck('id');
            $tag->contacts()->syncWithoutDetaching($ids);
        }

        AuditLog::record('groups.import', 'Impor anggota grup '.$group->subject, $result + ['tag' => $data['tag'] ?? null]);

        return back()->with('success', count($phones).' anggota diproses: '.$result['created'].' kontak baru'.(! empty($data['tag']) ? ', diberi tag "'.$data['tag'].'"' : '').'.');
    }

    private function qrDevices(Request $request)
    {
        return Device::where('provider', 'qr')->where('is_active', true)->where('status', 'connected')
            ->when($request->integer('device'), fn ($w, $id) => $w->whereKey($id))
            ->get();
    }
}
