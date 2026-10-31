<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Ticket;
use App\Models\User;

/**
 * Ticketing & helpdesk: buat, ubah status/prioritas/penugasan, SLA, eskalasi.
 * @2026-Alifiandra
 */
class TicketService
{
    public function __construct(
        private MessageService $messages,
        private WebhookDispatcher $webhooks,
    ) {}

    public function create(Contact $contact, array $data, ?User $by = null, bool $notifyCustomer = false): Ticket
    {
        $priority = $data['priority'] ?? 'normal';
        $conversation = $contact->conversation;

        $ticket = Ticket::create([
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'category' => $data['category'] ?? 'general',
            'priority' => $priority,
            'assigned_to' => $data['assigned_to'] ?? $conversation?->assigned_to ?? $by?->id,
            'created_by' => $by?->id,
            'sla_due_at' => now()->addHours(Ticket::slaHours($priority)),
        ])->refresh();

        $ticket->log('created', 'Tiket dibuat: '.$ticket->subject);

        if ($ticket->assigned_to && $ticket->assigned_to !== $by?->id) {
            AppNotification::notify('ticket.assigned', 'Tiket '.$ticket->number.' ditugaskan ke Anda', $ticket->subject, route('tickets.show', $ticket), (int) $ticket->assigned_to);
        }

        if ($notifyCustomer) {
            $this->notifyCustomer($ticket, "Halo {$contact->displayName()}, laporan Anda telah kami terima dengan nomor tiket *{$ticket->number}*. Tim kami akan segera menindaklanjuti.");
        }

        $this->webhooks->dispatch('ticket.created', ['ticket' => $ticket->toApiArray()]);

        return $ticket;
    }

    public function update(Ticket $ticket, array $data, bool $notifyCustomer = false): Ticket
    {
        foreach (['status', 'priority', 'assigned_to', 'category'] as $field) {
            if (! array_key_exists($field, $data) || $data[$field] == $ticket->{$field}) {
                continue;
            }

            $old = $ticket->{$field};
            $ticket->{$field} = $data[$field] ?: null;

            match ($field) {
                'status' => $ticket->log('status', 'Status: '.(Ticket::STATUSES[$old] ?? $old).' → '.$ticket->statusLabel()),
                'priority' => $ticket->log('priority', 'Prioritas: '.(Ticket::PRIORITIES[$old] ?? $old).' → '.Ticket::PRIORITIES[$ticket->priority]),
                'assigned_to' => $ticket->log('assigned', 'Ditugaskan ke '.(User::find($ticket->assigned_to)?->name ?? '—')),
                'category' => $ticket->log('status', 'Kategori: '.(Ticket::CATEGORIES[$ticket->category] ?? $ticket->category)),
            };

            if ($field === 'priority' && in_array($ticket->status, ['open', 'pending'], true)) {
                $ticket->sla_due_at = $ticket->created_at->copy()->addHours(Ticket::slaHours($ticket->priority));
                $ticket->escalated_at = null;
            }

            if ($field === 'assigned_to' && $ticket->assigned_to && $ticket->assigned_to !== auth()->id()) {
                AppNotification::notify('ticket.assigned', 'Tiket '.$ticket->number.' ditugaskan ke Anda', $ticket->subject, route('tickets.show', $ticket), (int) $ticket->assigned_to);
            }
        }

        if ($ticket->isDirty('status')) {
            $ticket->resolved_at = in_array($ticket->status, ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;

            if ($notifyCustomer && $ticket->status === 'resolved') {
                $this->notifyCustomer($ticket, "Tiket *{$ticket->number}* ({$ticket->subject}) telah diselesaikan. Terima kasih. Balas pesan ini bila masih ada kendala.");
            }
        }

        $ticket->save();
        $this->webhooks->dispatch('ticket.updated', ['ticket' => $ticket->toApiArray()]);

        return $ticket;
    }

    /** Eskalasi tiket yang melewati SLA (dipanggil scheduler). */
    public function escalateOverdue(): int
    {
        $count = 0;

        Ticket::withoutGlobalScopes()->overdue()->whereNull('escalated_at')->get()->each(function (Ticket $t) use (&$count) {
            \App\Support\Tenancy::run($t->tenant_id, function () use ($t, &$count) {
            $t->forceFill(['escalated_at' => now(), 'priority' => $t->priority === 'urgent' ? 'urgent' : ($t->priority === 'high' ? 'urgent' : 'high')])->save();
            $t->events()->create(['type' => 'escalated', 'body' => 'SLA terlewati — dieskalasi ke supervisor (prioritas '.Ticket::PRIORITIES[$t->priority].').']);
            AppNotification::notify('ticket.overdue', 'Tiket '.$t->number.' melewati SLA', $t->subject.' — '.$t->contact?->displayName(), route('tickets.show', $t), 'admins');
            $count++;
            });
        });

        return $count;
    }

    private function notifyCustomer(Ticket $ticket, string $text): void
    {
        $contact = $ticket->contact;
        $device = $ticket->conversation?->device?->is_active ? $ticket->conversation->device : Device::resolveDefault();

        if (! $device || ! $contact) {
            return;
        }

        $this->messages->queueText($device, $contact->phone, $text, auth()->user(), options: ['contact' => $contact, 'source' => 'ticket']);
        $ticket->log('notified', 'Info dikirim ke pelanggan via WhatsApp.');
    }
}
