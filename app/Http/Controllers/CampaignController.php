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

    public function create()
    {
        return view('campaigns.form', [
            'campaign' => new Campaign(['rate_per_minute' => (int) Setting::get('default_rate_per_minute'), 'audience' => []]),
            'devices' => Device::where('is_active', true)->orderByDesc('is_default')->get(),
            'templates' => Template::where('is_active', true)->with('variants')->orderBy('name')->get(),
            'tags' => Tag::withCount('contacts')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'device_id' => ['required', 'integer', 'exists:devices,id'],
            'template_id' => ['required', 'integer', 'exists:templates,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:tags,id'],
            'stages' => ['nullable', 'array'],
            'stages.*' => [Rule::in(array_keys(Contact::STAGES))],
            'rate_per_minute' => ['required', 'integer', 'min:1', 'max:120'],
            'mode' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'scheduled_at' => ['nullable', 'required_if:mode,schedule', 'date', 'after:now'],
            'file' => ['nullable', 'file', 'mimes:'.\App\Services\MediaStorage::EXTENSIONS, 'max:'.\App\Services\MediaStorage::MAX_KB],
        ], [], ['template_id' => 'template', 'device_id' => 'perangkat', 'scheduled_at' => 'jadwal', 'file' => 'lampiran']);

        $media = $request->hasFile('file') ? app(\App\Services\MediaStorage::class)->storeUpload($request->file('file')) : null;

        $campaign = Campaign::create([
            'name' => $data['name'],
            'device_id' => $data['device_id'],
            'template_id' => $data['template_id'],
            'media_type' => $media?->type,
            'media_path' => $media?->path,
            'media_name' => $media?->name,
            'media_mime' => $media?->mime,
            'media_size' => $media?->size,
            'audience' => ['tags' => array_values($data['tags'] ?? []), 'stages' => array_values($data['stages'] ?? [])],
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

        return redirect()->route('campaigns.show', $campaign)->with('success', 'Campaign disimpan.');
    }

    public function show(Campaign $campaign)
    {
        $campaign->load(['device', 'template.variants', 'creator']);

        $variantStats = DB::table('campaign_recipients as r')
            ->leftJoin('messages as m', 'm.id', '=', 'r.message_id')
            ->where('r.campaign_id', $campaign->id)
            ->whereNotNull('r.template_variant_id')
            ->selectRaw("r.template_variant_id as id, count(*) as total, sum(case when m.status in ('sent','delivered','read') then 1 else 0 end) as sent, sum(case when m.status = 'read' then 1 else 0 end) as read_count, sum(case when m.status = 'failed' then 1 else 0 end) as failed")
            ->groupBy('r.template_variant_id')
            ->get()->keyBy('id');

        return view('campaigns.show', [
            'campaign' => $campaign,
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

    public function destroy(Campaign $campaign)
    {
        abort_if($campaign->status === 'running', 422, 'Jeda atau batalkan campaign terlebih dahulu.');
        AuditLog::record('campaign.delete', 'Hapus campaign '.$campaign->name);
        $campaign->delete();

        return redirect()->route('campaigns.index')->with('success', 'Campaign dihapus.');
    }
}
