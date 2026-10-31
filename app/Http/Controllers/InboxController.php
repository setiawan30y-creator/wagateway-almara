<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\User;
use App\Services\InboundService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Unified Inbox: daftar percakapan, thread, balas, catatan internal, assign, status.
 * @2026-Alifiandra
 */
class InboxController extends Controller
{
    public function index(Request $request)
    {
        return view('inbox.index', $this->listData($request) + ['conversation' => null]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $conversation->load(['contact.tags', 'group', 'device', 'assignee', 'notes.user']);

        if ($conversation->unread_count > 0) {
            $conversation->forceFill(['unread_count' => 0])->save();
        }

        $messages = $conversation->messages()->with('user')->latest('id')->limit(100)->get()->reverse()->values();

        return view('inbox.index', $this->listData($request) + [
            'conversation' => $conversation,
            'thread' => $messages,
            'devices' => Device::where('is_active', true)->orderByDesc('is_default')->get(),
            'agents' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'templates' => \App\Models\Template::where('is_active', true)->with('variants')->orderBy('name')->get(),
        ]);
    }

    public function reply(Request $request, Conversation $conversation, MessageService $service)
    {
        $data = $request->validate([
            'body' => ['required_without:file', 'nullable', 'string', 'max:'.config('whatsapp.max_text_length')],
            'file' => ['nullable', 'file', 'mimes:'.\App\Services\MediaStorage::EXTENSIONS, 'max:'.\App\Services\MediaStorage::MAX_KB],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
        ], ['body.required_without' => 'Tulis pesan atau pilih lampiran.'], ['body' => 'pesan', 'file' => 'lampiran']);

        $device = isset($data['device_id'])
            ? Device::where('is_active', true)->findOrFail($data['device_id'])
            : ($conversation->device?->is_active ? $conversation->device : Device::resolveDefault());

        abort_unless($device, 422, 'Belum ada perangkat aktif.');

        $media = $request->hasFile('file') ? app(\App\Services\MediaStorage::class)->storeUpload($request->file('file')) : null;

        if ($conversation->isGroup()) {
            $message = $service->queueGroup($conversation->group, (string) ($data['body'] ?? ''), $request->user(), options: ['media' => $media]);

            return redirect()->route('inbox.show', $conversation)
                ->with($message->status === 'failed' ? 'warning' : 'success', $message->status === 'failed' ? 'Gagal mengirim: '.$message->error : null);
        }

        $contact = $conversation->contact;
        $message = $service->queueText($device, $contact->phone, (string) ($data['body'] ?? ''), $request->user(), options: ['contact' => $contact, 'media' => $media]);

        if (! $conversation->assigned_to) {
            $conversation->forceFill(['assigned_to' => $request->user()->id])->save();
        }

        return redirect()->route('inbox.show', $conversation)
            ->with($message->status === 'failed' ? 'warning' : 'success', $message->status === 'failed' ? 'Gagal mengirim: '.$message->error : null);
    }

    public function update(Request $request, Conversation $conversation)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Conversation::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Conversation::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $changes = array_filter([
            'status' => $data['status'] ?? null,
            'priority' => $data['priority'] ?? null,
        ]);

        if ($request->has('assigned_to')) {
            $changes['assigned_to'] = $data['assigned_to'] ?? null;

            if ($changes['assigned_to'] && $changes['assigned_to'] !== $conversation->assigned_to) {
                \App\Models\AppNotification::notify('conversation.assigned', 'Percakapan ditugaskan ke Anda',
                    $conversation->title(), route('inbox.show', $conversation), (int) $changes['assigned_to']);
            }
        }

        $conversation->forceFill($changes)->save();

        return back();
    }

    public function note(Request $request, Conversation $conversation)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']], [], ['body' => 'catatan']);

        $conversation->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return back()->with('success', 'Catatan internal disimpan.');
    }

    /** Simulasi pesan masuk (untuk uji coba tanpa provider sungguhan). */
    public function simulate(Request $request, InboundService $inbound)
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
        ], [], ['from' => 'nomor pengirim', 'message' => 'pesan']);

        $device = Device::where('is_active', true)->where('provider', 'log')->first() ?? Device::resolveDefault();
        abort_unless($device, 422, 'Belum ada perangkat aktif.');

        $message = $inbound->receive($device, $data['from'], $data['message'], null, $data['name'] ?? null);

        if (! $message) {
            return back()->with('warning', 'Nomor pengirim tidak valid.');
        }

        AuditLog::record('inbox.simulate', 'Simulasi pesan masuk dari '.$message->to);

        return redirect()->route('inbox.show', $message->conversation_id)->with('success', 'Pesan masuk simulasi diterima.');
    }

    /** Dipakai polling ringan di halaman inbox. */
    public function poll()
    {
        return response()->json([
            'unread' => (int) Conversation::sum('unread_count'),
            'latest' => (int) Message::max('id'),
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate(['contact_id' => ['required', 'integer', 'exists:contacts,id']]);
        $contact = Contact::findOrFail($data['contact_id']);

        return redirect()->route('inbox.show', Conversation::forContact($contact, Device::resolveDefault()));
    }

    private function listData(Request $request): array
    {
        $filter = $request->query('filter', 'open');
        $q = trim((string) $request->query('q'));
        $user = $request->user();

        $conversations = Conversation::with(['contact', 'group', 'assignee'])
            ->when($filter === 'open', fn ($w) => $w->where('status', 'open'))
            ->when($filter === 'pending', fn ($w) => $w->where('status', 'pending'))
            ->when($filter === 'closed', fn ($w) => $w->where('status', 'closed'))
            ->when($filter === 'unread', fn ($w) => $w->where('unread_count', '>', 0))
            ->when($filter === 'mine', fn ($w) => $w->where('assigned_to', $user->id))
            ->when($filter === 'unassigned', fn ($w) => $w->whereNull('assigned_to')->where('status', '!=', 'closed'))
            ->when($filter === 'groups', fn ($w) => $w->whereNotNull('group_id'))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->whereHas('contact', fn ($c) => $c->search($q))
                ->orWhereHas('group', fn ($g) => $g->where('subject', 'like', '%'.$q.'%'))))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return [
            'conversations' => $conversations,
            'filter' => $filter,
            'counts' => [
                'open' => Conversation::where('status', 'open')->count(),
                'unread' => Conversation::where('unread_count', '>', 0)->count(),
                'mine' => Conversation::where('assigned_to', $user->id)->where('status', '!=', 'closed')->count(),
            ],
        ];
    }
}
