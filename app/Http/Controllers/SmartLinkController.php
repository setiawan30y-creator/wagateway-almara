<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\SmartLink;
use App\Models\Tag;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Smart Link: kelola link + redirect publik /l/{slug}.
 * @2026-Alifiandra
 */
class SmartLinkController extends Controller
{
    public function index()
    {
        return view('smart-links.index', ['links' => SmartLink::with(['device', 'tag'])->withCount(['contacts as conversions_count' => fn ($q) => $q->where('stage', 'customer')])->latest('id')->get()]);
    }

    public function create()
    {
        return view('smart-links.form', ['link' => new SmartLink(['is_active' => true, 'message' => 'Halo, saya ingin bertanya.']), 'devices' => Device::orderBy('name')->get(), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $link = SmartLink::create($this->validated($request) + ['created_by' => $request->user()->id]);
        AuditLog::record('smartlink.create', 'Buat smart link /l/'.$link->slug);

        return redirect()->route('smart-links.show', $link)->with('success', 'Smart link dibuat.');
    }

    public function show(SmartLink $smartLink)
    {
        $days = collect(range(13, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->format('Y-m-d') => 0])->all();
        $smartLink->clicks()->where('created_at', '>=', now()->subDays(13)->startOfDay())->get(['created_at'])
            ->each(function ($c) use (&$days) {
                $d = $c->created_at->format('Y-m-d');
                if (isset($days[$d])) {
                    $days[$d]++;
                }
            });

        return view('smart-links.show', [
            'link' => $smartLink->load(['device', 'tag']),
            'daily' => $days,
            'devices' => $smartLink->clicks()->select('device', DB::raw('count(*) c'))->groupBy('device')->pluck('c', 'device'),
            'referers' => $smartLink->clicks()->whereNotNull('referer')->select('referer', DB::raw('count(*) c'))->groupBy('referer')->orderByDesc('c')->limit(5)->pluck('c', 'referer'),
            'contacts' => $smartLink->contacts()->latest('id')->limit(20)->get(),
            'conversions' => $smartLink->conversions(),
        ]);
    }

    public function edit(SmartLink $smartLink)
    {
        return view('smart-links.form', ['link' => $smartLink, 'devices' => Device::orderBy('name')->get(), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function update(Request $request, SmartLink $smartLink)
    {
        $smartLink->update($this->validated($request, $smartLink));

        return redirect()->route('smart-links.show', $smartLink)->with('success', 'Smart link diperbarui.');
    }

    public function destroy(SmartLink $smartLink)
    {
        AuditLog::record('smartlink.delete', 'Hapus smart link /l/'.$smartLink->slug);
        $smartLink->delete();

        return redirect()->route('smart-links.index')->with('success', 'Smart link dihapus.');
    }

    /** Redirect publik: catat klik lalu arahkan ke wa.me. */
    public function go(Request $request, string $slug)
    {
        $link = SmartLink::where('slug', strtolower($slug))->first();

        if (! $link || ! $link->isAvailable()) {
            return response()->view('smart-links.unavailable', [], 404);
        }

        $ua = (string) $request->userAgent();
        $link->clicks()->create([
            'visitor' => hash('sha256', $request->ip().'|'.$ua.'|'.config('app.key')),
            'referer' => mb_substr((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST), 0, 255) ?: null,
            'device' => preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? 'mobile' : 'desktop',
        ]);
        $link->increment('clicks_count');

        return redirect()->away($link->whatsappUrl());
    }

    private function validated(Request $request, ?SmartLink $link = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('name')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('smart_links', 'slug')->ignore($link?->id)],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:1000'],
            'tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
        ], ['slug.unique' => 'Slug sudah dipakai link lain.'], ['slug' => 'slug']);

        $data['phone'] = ! empty($data['phone']) ? PhoneNumber::normalize($data['phone']) : null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
