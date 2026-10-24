<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ticketing & Helpdesk.
 * @2026-Alifiandra
 */
class TicketController extends Controller
{
    public function __construct(private TicketService $service) {}

    public function index(Request $request)
    {
        $filter = $request->query('filter', 'open');
        $user = $request->user();

        $tickets = Ticket::with(['contact', 'assignee'])
            ->when($filter === 'open', fn ($q) => $q->open())
            ->when($filter === 'mine', fn ($q) => $q->open()->where('assigned_to', $user->id))
            ->when($filter === 'overdue', fn ($q) => $q->overdue())
            ->when($filter === 'resolved', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->when($request->query('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
            ->when(trim((string) $request->query('q')) !== '', function ($q) use ($request) {
                $term = trim((string) $request->query('q'));
                $q->where(fn ($w) => $w->where('number', 'like', "%{$term}%")->orWhere('subject', 'like', "%{$term}%")
                    ->orWhereHas('contact', fn ($c) => $c->search($term)));
            })
            ->orderByRaw("case when status in ('open','pending') then 0 else 1 end")
            ->orderBy('sla_due_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filter' => $filter,
            'counts' => [
                'open' => Ticket::open()->count(),
                'mine' => Ticket::open()->where('assigned_to', $user->id)->count(),
                'overdue' => Ticket::overdue()->count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $contact = $request->query('contact') ? Contact::find($request->query('contact')) : null;
        $conversation = $request->query('conversation') ? Conversation::with('contact')->find($request->query('conversation')) : null;
        $contact ??= $conversation?->contact;

        return view('tickets.form', [
            'contact' => $contact,
            'agents' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'prefill' => $conversation ? (string) $conversation->messages()->where('direction', 'in')->latest('id')->value('body') : '',
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'phone' => ['required_without:contact_id', 'nullable', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ], ['phone.required_without' => 'Pilih kontak atau isi nomor WhatsApp pelanggan.'], ['subject' => 'judul']);

        $contact = isset($data['contact_id']) ? Contact::findOrFail($data['contact_id']) : Contact::findOrCreateByPhone($data['phone'], ['source' => 'ticket']);

        $ticket = $this->service->create($contact, $data, $request->user(), $request->boolean('notify_customer'));

        return redirect()->route('tickets.show', $ticket)->with('success', 'Tiket '.$ticket->number.' dibuat.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['contact.tags', 'assignee', 'creator', 'events.user', 'conversation']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'agents' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'recent' => $ticket->contact?->messages()->latest('id')->limit(8)->get()->reverse() ?? collect(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
            'category' => ['nullable', Rule::in(array_keys(Ticket::CATEGORIES))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $changes = array_filter(collect($data)->only(['status', 'priority', 'category'])->all(), fn ($v) => $v !== null);

        if ($request->has('assigned_to')) {
            $changes['assigned_to'] = $data['assigned_to'] ?? null;
        }

        $this->service->update($ticket, $changes, $request->boolean('notify_customer'));

        return back()->with('success', 'Tiket diperbarui.');
    }

    public function note(Request $request, Ticket $ticket)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']], [], ['body' => 'catatan']);
        $ticket->log('note', $data['body']);
        $ticket->touch();

        return back()->with('success', 'Catatan ditambahkan.');
    }
}
