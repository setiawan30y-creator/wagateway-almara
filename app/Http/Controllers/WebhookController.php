<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverWebhook;
use App\Models\AuditLog;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\WebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Webhook keluar (event ke sistem lain).
 * @2026-Alifiandra
 */
class WebhookController extends Controller
{
    public function index()
    {
        return view('webhooks.index', [
            'webhooks' => Webhook::withCount('deliveries')->orderBy('id')->get(),
            'deliveries' => WebhookDelivery::with('webhook')->latest('id')->limit(30)->get(),
        ]);
    }

    public function create()
    {
        return view('webhooks.form', ['webhook' => new Webhook(['events' => array_keys(Webhook::EVENTS), 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $secret = 'whsec_'.Str::random(32);
        $webhook = Webhook::create($data + ['secret' => $secret]);

        AuditLog::record('webhook.create', 'Buat webhook '.$webhook->name);

        return redirect()->route('webhooks.index')
            ->with('success', 'Webhook dibuat. Simpan signing secret — hanya ditampilkan sekali.')
            ->with('plain_secret', $secret);
    }

    public function edit(Webhook $webhook)
    {
        return view('webhooks.form', compact('webhook'));
    }

    public function update(Request $request, Webhook $webhook)
    {
        $webhook->update($this->validated($request));
        AuditLog::record('webhook.update', 'Ubah webhook '.$webhook->name);

        return redirect()->route('webhooks.index')->with('success', 'Webhook diperbarui.');
    }

    public function destroy(Webhook $webhook)
    {
        AuditLog::record('webhook.delete', 'Hapus webhook '.$webhook->name);
        $webhook->delete();

        return redirect()->route('webhooks.index')->with('success', 'Webhook dihapus.');
    }

    public function rotate(Webhook $webhook)
    {
        $secret = 'whsec_'.Str::random(32);
        $webhook->update(['secret' => $secret]);
        AuditLog::record('webhook.rotate', 'Rotasi secret webhook '.$webhook->name);

        return back()->with('success', 'Secret baru dibuat. Perbarui di sistem penerima.')->with('plain_secret', $secret);
    }

    public function test(Webhook $webhook)
    {
        $eventId = 'evt_'.Str::lower((string) Str::ulid());
        $delivery = $webhook->deliveries()->create([
            'event_id' => $eventId,
            'event' => 'ping',
            'payload' => ['id' => $eventId, 'event' => 'ping', 'created_at' => now()->toIso8601String(), 'data' => ['message' => 'Tes webhook ALMARA Gateway WA']],
        ]);

        DeliverWebhook::dispatchSync($delivery->id);
        $delivery->refresh();

        return back()->with($delivery->delivered_at ? 'success' : 'warning', $delivery->delivered_at
            ? 'Tes berhasil (HTTP '.$delivery->response_status.').'
            : 'Tes gagal: '.($delivery->error ?? 'tidak diketahui'));
    }

    public function redeliver(WebhookDelivery $delivery)
    {
        $delivery->update(['delivered_at' => null]);
        DeliverWebhook::dispatchSync($delivery->id);

        return back()->with('success', 'Event dikirim ulang.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'url' => ['required', 'url', 'max:500', 'starts_with:http://,https://'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(array_keys(Webhook::EVENTS))],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['events' => 'event']);

        return [
            'name' => $data['name'],
            'url' => $data['url'],
            'events' => array_values($data['events']),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
