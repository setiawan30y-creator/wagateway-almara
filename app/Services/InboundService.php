<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Support\PhoneNumber;

/**
 * Memproses pesan masuk dari provider: kontak, percakapan, STOP/START, notifikasi, webhook.
 * @2026-Alifiandra
 */
class InboundService
{
    public function __construct(
        private MessageService $messages,
        private WebhookDispatcher $webhooks,
    ) {}

    public function receive(Device $device, string $from, string $body, ?string $providerMessageId = null, ?string $profileName = null): ?Message
    {
        $phone = PhoneNumber::normalize($from);

        if (! PhoneNumber::isValid($phone) || trim($body) === '') {
            return null;
        }

        // Idempotensi: callback duplikat dari provider diabaikan.
        if ($providerMessageId && Message::where('provider_message_id', $providerMessageId)->where('direction', 'in')->exists()) {
            return null;
        }

        $contact = Contact::findOrCreateByPhone($phone, ['name' => $profileName, 'source' => 'whatsapp']);

        if (! $contact->name && $profileName) {
            $contact->forceFill(['name' => mb_substr($profileName, 0, 150)])->save();
        }

        $conversation = Conversation::forContact($contact, $device);
        $isNewConversation = $conversation->wasRecentlyCreated;

        $message = Message::create([
            'device_id' => $device->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'to' => $phone,
            'body' => mb_substr($body, 0, 10000),
            'status' => 'received',
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
        ]);

        $conversation->touchWith($message);
        $contact->forceFill(['last_message_at' => now()])->save();
        $device->forceFill(['status' => 'connected', 'last_seen_at' => now()])->save();

        if ($isNewConversation || ! $conversation->assigned_to) {
            $this->autoAssign($conversation);
        }

        $this->handleKeywords($device, $contact, trim($body));

        AppNotification::notify(
            'message.received',
            'Pesan baru dari '.$contact->displayName(),
            mb_substr($body, 0, 120),
            route('inbox.show', $conversation),
            $conversation->assigned_to ?? 'all',
        );

        $this->webhooks->dispatch('message.received', [
            'message' => $message->toApiArray(),
            'contact' => $contact->load('tags')->toApiArray(),
        ]);

        return $message;
    }

    /** Deteksi STOP / START (opt-out & opt-in). */
    private function handleKeywords(Device $device, Contact $contact, string $body): void
    {
        $word = mb_strtolower(trim($body, " \t\n\r\0\x0B.!"));

        if (in_array($word, Setting::keywords('stop_keywords'), true) && $contact->consent !== 'opted_out') {
            $contact->setConsent('opted_out', 'keyword', 'Membalas "'.$body.'"');
            $this->reply($device, $contact, (string) Setting::get('stop_reply'));
            AppNotification::notify('contact.opted_out', $contact->displayName().' berhenti berlangganan', null, route('contacts.show', $contact), 'admins');
            $this->webhooks->dispatch('contact.opted_out', ['contact' => $contact->toApiArray()]);

            return;
        }

        if (in_array($word, Setting::keywords('start_keywords'), true) && $contact->consent !== 'opted_in') {
            $contact->setConsent('opted_in', 'keyword', 'Membalas "'.$body.'"');
            $this->reply($device, $contact, (string) Setting::get('start_reply'));
        }
    }

    private function reply(Device $device, Contact $contact, string $text): void
    {
        if (trim($text) !== '') {
            $this->messages->queueText($device, $contact->phone, $text, options: ['contact' => $contact, 'source' => 'auto']);
        }
    }

    /** Penugasan otomatis round-robin ke agent aktif (bila diaktifkan). */
    private function autoAssign(Conversation $conversation): void
    {
        if (Setting::get('auto_assign') !== 'round_robin') {
            return;
        }

        $agents = User::where('is_active', true)->orderBy('id')->pluck('id');

        if ($agents->isEmpty()) {
            return;
        }

        $last = (int) Setting::get('auto_assign_last', '0');
        $next = $agents->first(fn ($id) => $id > $last) ?? $agents->first();

        $conversation->forceFill(['assigned_to' => $next])->save();
        Setting::set('auto_assign_last', (string) $next);
    }
}
