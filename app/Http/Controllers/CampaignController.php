<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Template;
use App\Services\CampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Broadcast Center.
 * @2026-Alifiandra
 */
class CampaignController extends Controller
{
    public function __construct(private CampaignService $service) {}

    public function index()
    {
        return view('campaigns.index', [
            'campaigns' => Campaign::with(['device', 'template'])->latest('id')->paginate(25),
        ]);
    }

    public function create(Request $request)
    {
        // Duplikat: ?dari={id} -> isi form dari campaign lama (ganti rombongan / jadwal saja)
        $from = $request->integer('dari') ? Campaign::find($request->integer('dari')) : null;

        $campaign = $from
            ? new Campaign([
                'name' => $from->name.' (salinan)', 'device_id' => $from->device_id, 'template_ids' => $from->templateIds(), 'variant_ids' => $from->variantIds(),
                'audience' => $from->audience ?? [], 'rate_per_minute' => $from->rate_per_minute,
            ])
            : new Campaign(['rate_per_minute' => (int) Setting::get('default_rate_per_minute'), 'audience' => ['lists' => array_filter([$request->integer('rombongan')])]]);

        return view('campaigns.form', [
            'campaign' => $campaign,
            'from' => $from,
            'devices' => Device::where('is_active', true)->orderByDesc('is_default')->get(),
            'templates' => Template::where('is_active', true)->with('variants')->orderBy('name')->get(),
            'tags' => Tag::withCount('contacts')->orderBy('name')->get(),
            'lists' => \App\Models\RecipientList::withCount('contacts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        // kompatibel dengan form/klien lama yang mengirim satu template_id
        if (! $request->has('template_ids') && $request->filled('template_id')) {
            $request->merge(['template_ids' => [(int) $request->input('template_id')]]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'device_id' => ['required', 'integer', \App\Support\Tenancy::exists('devices')],
            'template_ids' => ['required', 'array', 'min:1'],
            'template_ids.*' => ['integer', \App\Support\Tenancy::exists('templates')],
            'variant_ids' => ['nullable', 'array'],
            'variant_ids.*' => ['integer'],
            'lists' => ['nullable', 'array'],
            'lists.*' => ['integer', \App\Support\Tenancy::exists('recipient_lists')],
            'copy_media_from' => ['nullable', 'integer', \App\Support\Tenancy::exists('campaigns')],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', \App\Support\Tenancy::exists('tags')],
            'stages' => ['nullable', 'array'],
            'stages.*' => [Rule::in(array_keys(Contact::STAGES))],
            'rate_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'mode' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'scheduled_at' => ['nullable', 'required_if:mode,schedule', 'date', 'after:now'],
            'file' => ['nullable', 'file', 'mimes:'.\App\Services\MediaStorage::EXTENSIONS, 'max:'.\App\Services\MediaStorage::MAX_KB],
        ], ['template_ids.required' => 'Pilih minimal satu template.'], ['template_ids' => 'template', 'device_id' => 'perangkat', 'scheduled_at' => 'jadwal', 'file' => 'lampiran']);

        $media = $request->hasFile('file') ? app(\App\Services\MediaStorage::class)->storeUpload($request->file('file')) : null;
        // duplikat campaign: pakai lampiran campaign asal bila tidak mengunggah yang baru
        $media ??= isset($data['copy_media_from']) ? Campaign::find($data['copy_media_from'])?->mediaFile() : null;
        $templateIds = array_values(array_unique(array_map('intval', $data['template_ids'])));

        // varian terpilih harus milik template terpilih; semua varian template tercentang = simpan null (semua)
        $variantIds = null;
        if (array_key_exists('variant_ids', $data)) {
            $all = \App\Models\TemplateVariant::whereIn('template_id', $templateIds)->pluck('id')->all();
            $picked = array_values(array_intersect(array_map('intval', (array) $data['variant_ids']), $all));

            if ($picked === []) {
                throw \Illuminate\Validation\ValidationException::withMessages(['template_ids' => 'Pilih minimal satu varian pesan.']);
            }

            $variantIds = count($picked) === count($all) ? null : $picked;
        }

        $campaign = Campaign::create([
            'name' => $data['name'],
            'device_id' => $data['device_id'],
            'template_id' => $templateIds[0],
            'template_ids' => $templateIds,
            'variant_ids' => $variantIds,
            'media_type' => $media?->type,
            'media_path' => $media?->path,
            'media_name' => $media?->name,
            'media_mime' => $media?->mime,
            'media_size' => $media?->size,
            'audience' => [
                'lists' => array_values(array_map('intval', $data['lists'] ?? [])),
                'tags' => array_values($data['tags'] ?? []),
                'stages' => array_values($data['stages'] ?? []),
            ],
            'rate_per_minute' => $data['rate_per_minute'],
            'status' => $data['mode'] === 'schedule' ? 'scheduled' : 'draft',
            'scheduled_at' => $data['mode'] === 'schedule' ? Carbon::parse($data['scheduled_at']) : null,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('campaign.create', 'Buat campaign '.$campaign->name, ['mode' => $data['mode']]);

        if ($data['mode'] === 'now') {
            $this->service->start($campaign);
            $this->service->runBatch($campaign->refresh());
            AuditLog::record('campaign.start', 'Mulai campaign '.$campaign->name);
        }

        $msg = match ($data['mode']) {
            'now' => Setting::inQuietHours() ? 'Campaign dimulai — sedang jam tenang, pengiriman otomatis lanjut setelah jam tenang selesai.' : 'Campaign mulai dikirim. Pesan terkirim bertahap; pantau progresnya di halaman ini.',
            'schedule' => 'Campaign dijadwalkan pada '.$campaign->scheduled_at->format('d/m/Y H:i').'.',
            default => 'Campaign disimpan sebagai draft. Klik “Kirim sekarang” bila sudah siap.',
        };

        return redirect()->route('campaigns.show', $campaign)->with('success', $msg);
    }

    public function show(Campaign $campaign)
    {
        $campaign->load(['device', 'creator']);

        $variantStats = DB::table('campaign_recipients as r')
            ->leftJoin('messages as m', 'm.id', '=', 'r.message_id')
            ->where('r.campaign_id', $campaign->id)
            ->whereNotNull('r.template_variant_id')
            ->selectRaw("r.template_variant_id as id, count(*) as total, sum(case when m.status in ('sent','delivered','read') then 1 else 0 end) as sent, sum(case when m.status = 'read' then 1 else 0 end) as read_count, sum(case when m.status = 'failed' then 1 else 0 end) as failed")
            ->groupBy('r.template_variant_id')
            ->get()->keyBy('id');

        return view('campaigns.show', [
            'campaign' => $campaign,
            'usedTemplates' => $campaign->usedTemplates(),
            'diagnosis' => $this->service->diagnose($campaign),
            'stats' => $campaign->stats(),
            'variantStats' => $variantStats,
            'recipients' => $campaign->recipients()->with(['contact', 'variant', 'message'])->latest('id')->paginate(50),
            'skipReasons' => $campaign->recipients()->where('status', 'skipped')->selectRaw('skip_reason, count(*) c')->groupBy('skip_reason')->pluck('c', 'skip_reason'),
            'quiet' => Setting::inQuietHours(),
        ]);
    }

    public function preview(Request $request)
    {
        return response()->json($this->service->preview([
            'lists' => array_map('intval', (array) $request->input('lists', [])),
            'tags' => array_map('intval', (array) $request->input('tags', [])),
            'stages' => (array) $request->input('stages', []),
        ]));
    }

    public function action(Request $request, Campaign $campaign, string $action)
    {
        match ($action) {
            'start' => $this->service->start($campaign),
            'pause' => $this->service->pause($campaign, 'Dijeda manual oleh '.$request->user()->name),
            'resume' => $this->service->resume($campaign),
            'cancel' => $this->service->cancel($campaign),
            'run' => null,
            default => abort(404),
        };

        $sent = in_array($action, ['start', 'resume', 'run'], true) ? $this->service->runBatch($campaign->refresh()) : 0;

        AuditLog::record('campaign.'.$action, ucfirst($action).' campaign '.$campaign->name);

        $msg = match ($action) {
            'start', 'resume', 'run' => Setting::inQuietHours() ? 'Sedang jam tenang — pengiriman menunggu.' : "Batch diproses: {$sent} pesan.",
            'pause' => 'Campaign dijeda.',
            'cancel' => 'Campaign dibatalkan.',
        };

        return back()->with('success', $msg);
    }

    /** Progres pengiriman realtime: nomor yang sedang dikirim, antrean berikutnya, dan yang baru terkirim. */
    public function live(Campaign $campaign)
    {
        $stats = $campaign->stats();
        $msgs = fn () => \App\Models\Message::with('contact:id,name,phone')->where('campaign_id', $campaign->id)->where('direction', 'out');
        $row = fn ($m, array $extra = []) => [
            'name' => $m->contact?->name ?: null,
            'phone' => $m->to,
        ] + $extra;

        // nomor yang sedang diproses engine (Scan QR)
        $current = null;
        $live = $campaign->device_id ? \Illuminate\Support\Facades\Cache::get('live.device.'.$campaign->device_id) : null;
        if ($live && now()->getTimestampMs() - (int) $live['at'] < 120000) {
            $m = $msgs()->where('provider_message_id', $live['id'])->whereNull('wa_sent_at')->where('status', '!=', 'failed')->first();
            if ($m) {
                $left = isset($live['wait_ms']) ? max(0, (int) ceil(((int) $live['at'] + (int) $live['wait_ms'] - now()->getTimestampMs()) / 1000)) : null;
                $current = $row($m, [
                    'state' => $live['state'],
                    'label' => ['waiting' => 'Menunggu jeda acak', 'typing' => 'Sedang mengetik…', 'sending' => 'Mengirim…'][$live['state']] ?? $live['state'],
                    'seconds_left' => in_array($live['state'], ['waiting', 'typing'], true) ? $left : null,
                ]);
            }
        }

        $queued = $msgs()->whereNull('wa_sent_at')->where('status', 'sent')->orderBy('id')
            ->when($current, fn ($q) => $q->where('to', '!=', $current['phone']))->limit(5)->get();

        $recent = $msgs()->where(fn ($q) => $q->whereNotNull('wa_sent_at')->orWhere('status', 'failed'))
            ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(wa_sent_at, updated_at)'))->limit(8)->get();

        return response()->json([
            'status' => $campaign->status,
            'status_label' => $campaign->statusLabel(),
            'running' => in_array($campaign->status, ['running', 'scheduled'], true),
            'stats' => [
                'delivered_to_wa' => $msgs()->whereNotNull('wa_sent_at')->count(),
                'in_engine' => $msgs()->whereNull('wa_sent_at')->where('status', 'sent')->count(),
                'waiting' => $stats['pending'],
                'failed' => $stats['failed'],
                'skipped' => $stats['skipped'],
                'target' => $stats['total'] - $stats['skipped'],
            ],
            'current' => $current,
            'queue' => $queued->map(fn ($m) => $row($m))->values(),
            'recent' => $recent->map(fn ($m) => $row($m, [
                'status' => $m->status,
                'error' => $m->status === 'failed' ? $m->error : null,
                'time' => ($m->wa_sent_at ?? $m->updated_at)?->format('H:i:s'),
            ]))->values(),
        ]);
    }

    /** Tambah / ganti / hapus lampiran campaign yang belum selesai. */
    public function attachment(Request $request, Campaign $campaign)
    {
        abort_unless(in_array($campaign->status, ['draft', 'scheduled', 'paused'], true), 422, 'Lampiran hanya bisa diubah saat campaign draft, terjadwal, atau dijeda.');

        if ($request->boolean('remove')) {
            $campaign->forceFill(['media_type' => null, 'media_path' => null, 'media_name' => null, 'media_mime' => null, 'media_size' => null])->save();
            AuditLog::record('campaign.attachment', 'Hapus lampiran campaign '.$campaign->name);

            return back()->with('success', 'Lampiran dihapus.');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:'.\App\Services\MediaStorage::EXTENSIONS, 'max:'.\App\Services\MediaStorage::MAX_KB],
        ], ['file.required' => 'Pilih foto / file terlebih dahulu.'], ['file' => 'lampiran']);

        $media = app(\App\Services\MediaStorage::class)->storeUpload($request->file('file'));
        $campaign->forceFill([
            'media_type' => $media->type, 'media_path' => $media->path, 'media_name' => $media->name,
            'media_mime' => $media->mime, 'media_size' => $media->size,
        ])->save();

        AuditLog::record('campaign.attachment', 'Lampiran campaign '.$campaign->name.': '.$media->name);

        return back()->with('success', 'Lampiran disimpan. Penerima berikutnya akan menerima lampiran ini.');
    }

    public function destroy(Campaign $campaign)
    {
        abort_if($campaign->status === 'running', 422, 'Jeda atau batalkan campaign terlebih dahulu.');
        AuditLog::record('campaign.delete', 'Hapus campaign '.$campaign->name);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign dihapus.');
    }
}
