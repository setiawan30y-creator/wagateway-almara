<?php

namespace App\AI;

use App\Models\AiLog;
use App\Models\AppNotification;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Device;
use App\Models\KnowledgeItem;
use App\Models\Message;
use App\Models\Setting;
use App\Services\MessageService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * AI Agent — menjawab chat pelanggan otomatis memakai OpenRouter (API kompatibel OpenAI).
 * Jawaban diambil dari Basis Pengetahuan; bila tidak tahu / pelanggan minta CS -> serahkan ke manusia.
 * @2026-Alifiandra
 */
class AiAgent
{
    public const HANDOFF = '[HANDOFF]';

    private const STOPWORDS = ['yang', 'dan', 'untuk', 'dengan', 'ini', 'itu', 'apa', 'ada', 'bisa', 'saya', 'kak', 'kakak', 'min', 'mau', 'tidak', 'gak', 'nggak', 'dari', 'ke', 'di', 'berapa', 'gimana', 'bagaimana', 'tolong', 'mohon', 'halo', 'hai', 'pak', 'bu', 'the', 'and', 'for', 'you', 'are', 'what', 'how'];

    public function __construct(private MessageService $messages) {}

    public function isConfigured(): bool
    {
        return \App\Support\License::allows('ai') && Setting::get('ai_enabled') === '1' && Setting::secret('ai_api_key') !== '';
    }

    /**
     * Balas pesan masuk bila aturan mengizinkan.
     * @return string status: replied | handoff | skipped:<alasan> | error
     */
    public function handleIncoming(Conversation $conversation, Message $incoming): string
    {
        $conversation->loadMissing(['contact', 'device']);
        $contact = $conversation->contact;

        if ($reason = $this->skipReason($conversation, $contact)) {
            return 'skipped:'.$reason;
        }

        $device = $conversation->device?->is_active ? $conversation->device : Device::resolveDefault();
        if (! $device) {
            return 'skipped:no-device';
        }

        // Pelanggan meminta CS manusia -> langsung serahkan tanpa memanggil AI
        if ($this->wantsHuman($incoming->body)) {
            $this->handoff($conversation, $device, 'Pelanggan meminta CS manusia.');
            AiLog::create(['conversation_id' => $conversation->id, 'status' => 'handoff', 'model' => null]);

            return 'handoff';
        }

        try {
            $answer = $this->complete($this->buildMessages($conversation, $incoming->body), 'reply', $conversation->id);
        } catch (RuntimeException $e) {
            return 'error';
        }

        if ($answer === '' || str_contains($answer, self::HANDOFF)) {
            $this->handoff($conversation, $device, 'AI tidak menemukan jawaban yang tepat.');

            return 'handoff';
        }

        $this->messages->queueText($device, $contact->phone, $answer, options: ['contact' => $contact, 'ai' => true, 'source' => 'auto']);

        return 'replied';
    }

    /** Saran balasan untuk CS (Copilot) — tidak dikirim otomatis. */
    public function suggest(Conversation $conversation): string
    {
        $last = $conversation->messages()->where('direction', 'in')->latest('id')->value('body') ?? '';
        $answer = $this->complete($this->buildMessages($conversation, (string) $last, forAgent: true), 'suggest', $conversation->id);

        return trim(str_replace(self::HANDOFF, '', $answer));
    }

    /** Uji AI dari panel tanpa WhatsApp. @param array<int, array{role:string, content:string}> $history */
    public function test(string $question, array $history = []): string
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($this->retrieve($question), null)]];

        foreach (array_slice($history, -10) as $h) {
            if (in_array($h['role'] ?? '', ['user', 'assistant'], true) && trim((string) ($h['content'] ?? '')) !== '') {
                $messages[] = ['role' => $h['role'], 'content' => mb_substr((string) $h['content'], 0, 2000)];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $question];

        return $this->complete($messages, 'test');
    }

    public function skipReason(Conversation $conversation, ?Contact $contact): ?string
    {
        return match (true) {
            ! $this->isConfigured() => 'ai-off',
            ! $contact => 'no-contact',
            $contact->consent === 'opted_out' => 'opted-out',
            ! $conversation->ai_enabled => 'ai-off-conversation',
            $conversation->aiPaused() => 'paused',
            Setting::get('ai_mode') === 'outside_hours' && Setting::inWorkingHours() => 'working-hours',
            Setting::get('ai_mode') === 'unassigned' && $conversation->assigned_to !== null => 'assigned',
            $this->dailyCount() >= (int) Setting::get('ai_daily_limit') => 'daily-limit',
            $this->contactHourCount($contact) >= (int) Setting::get('ai_max_per_contact_hour') => 'contact-limit',
            default => null,
        };
    }

    public function wantsHuman(string $text): bool
    {
        $text = mb_strtolower($text);

        foreach (Setting::keywords('ai_handoff_keywords') as $kw) {
            if ($kw !== '' && preg_match('/(^|\W)'.preg_quote($kw, '/').'($|\W)/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** Ambil potongan pengetahuan yang relevan (pencarian kata kunci sederhana). */
    public function retrieve(string $query, int $limit = 5): Collection
    {
        $items = KnowledgeItem::where('is_active', true)->get();

        // Basis pengetahuan kecil: kirim semuanya agar AI punya konteks lengkap.
        if ($items->count() <= 8 && $items->sum(fn ($i) => mb_strlen($i->title.$i->content)) <= 6000) {
            return $items;
        }

        $terms = $this->terms($query);

        return $items
            ->map(function (KnowledgeItem $i) use ($terms) {
                $title = mb_strtolower($i->title);
                $body = mb_strtolower($i->content);
                $score = 0;
                foreach ($terms as $t) {
                    $score += substr_count($title, $t) * 3 + min(substr_count($body, $t), 5);
                }
                $i->setAttribute('score', $score);

                return $i;
            })
            ->filter(fn ($i) => $i->score > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    public function systemPrompt(Collection $knowledge, ?Contact $contact, bool $forAgent = false): string
    {
        $name = Setting::get('ai_agent_name');
        $kb = $knowledge->isEmpty()
            ? '(Belum ada data pengetahuan.)'
            : $knowledge->map(fn ($k) => '### '.$k->title."\n".trim($k->content))->implode("\n\n");

        $lines = [
            "Nama kamu: {$name}.",
            (string) Setting::get('ai_persona'),
            '',
            'ATURAN WAJIB:',
            '- Jawab HANYA berdasarkan DATA BISNIS di bawah. Jangan mengarang harga, stok, promo, alamat, nomor rekening, atau kebijakan yang tidak tertulis.',
            '- Gunakan bahasa yang sama dengan pelanggan (default Bahasa Indonesia). Singkat, ramah, cocok untuk WhatsApp (maks. ±5 kalimat). Boleh *tebal* gaya WhatsApp, tanpa markdown lain.',
            '- Jangan meminta atau menyebutkan data sensitif (PIN, OTP, password, nomor kartu).',
            $forAgent
                ? '- Kamu menyusun DRAF balasan untuk CS manusia. Tulis draf balasan saja tanpa penjelasan tambahan.'
                : '- Jika jawaban tidak ada di DATA BISNIS, pertanyaan di luar topik bisnis, pelanggan marah/komplain serius, atau meminta bicara dengan manusia: balas PERSIS dengan '.self::HANDOFF.' saja.',
        ];

        if ($rules = trim((string) Setting::get('ai_rules'))) {
            $lines[] = '- '.str_replace("\n", "\n- ", $rules);
        }

        if ($contact?->name) {
            $lines[] = '';
            $lines[] = 'Nama pelanggan: '.$contact->name.'.';
        }

        $lines[] = '';
        $lines[] = 'Waktu sekarang: '.now()->translatedFormat('l, d F Y H:i').' WIB.';
        $lines[] = '';
        $lines[] = '=== DATA BISNIS ===';
        $lines[] = $kb;

        return implode("\n", $lines);
    }

    /** @return array<int, array{role:string, content:string}> */
    private function buildMessages(Conversation $conversation, string $latest, bool $forAgent = false): array
    {
        $history = $conversation->messages()->latest('id')->limit(12)->get()->reverse();
        $context = trim($history->where('direction', 'in')->take(-3)->pluck('body')->implode(' ').' '.$latest);

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($this->retrieve($context), $conversation->contact, $forAgent)]];

        foreach ($history as $m) {
            $text = trim($m->preview());
            if ($text === '') {
                continue;
            }
            $messages[] = ['role' => $m->direction === 'in' ? 'user' : 'assistant', 'content' => mb_substr($text, 0, 2000)];
        }

        // Pastikan pesan terakhir dari pelanggan
        if (end($messages)['role'] !== 'user' && $latest !== '') {
            $messages[] = ['role' => 'user', 'content' => $latest];
        }

        return $messages;
    }

    /** Panggil API chat completions. */
    public function complete(array $messages, string $purpose, ?int $conversationId = null): string
    {
        $model = (string) Setting::get('ai_model');
        $started = microtime(true);
        $log = ['conversation_id' => $conversationId, 'purpose' => $purpose, 'model' => $model];

        $key = Setting::secret('ai_api_key');
        if ($key === '') {
            AiLog::create($log + ['status' => 'error', 'error' => 'API key belum diisi']);

            throw new RuntimeException('API key AI belum diisi di menu AI Agent.');
        }

        try {
            $response = Http::baseUrl(rtrim((string) Setting::get('ai_base_url'), '/'))
                ->withToken($key)
                ->withHeaders(['HTTP-Referer' => config('app.url'), 'X-Title' => 'ALMARA Gateway WA'])
                ->acceptJson()
                ->timeout(60)
                ->post('/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => (float) Setting::get('ai_temperature'),
                    'max_tokens' => 600,
                ]);
        } catch (ConnectionException $e) {
            AiLog::create($log + ['status' => 'error', 'error' => 'Tidak dapat terhubung ke layanan AI', 'latency_ms' => (int) ((microtime(true) - $started) * 1000)]);

            throw new RuntimeException('Tidak dapat terhubung ke layanan AI.');
        }

        $latency = (int) ((microtime(true) - $started) * 1000);

        if (! $response->successful()) {
            $err = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());
            AiLog::create($log + ['status' => 'error', 'error' => mb_substr($err, 0, 250), 'latency_ms' => $latency]);

            throw new RuntimeException('Layanan AI: '.$err);
        }

        $text = trim((string) $response->json('choices.0.message.content', ''));
        // Bersihkan blok "pikiran" dari model reasoning bila ada
        $text = trim((string) preg_replace('/<think>.*?<\/think>/s', '', $text));

        AiLog::create($log + [
            'model' => (string) ($response->json('model') ?: $model),
            'status' => str_contains($text, self::HANDOFF) ? 'handoff' : 'ok',
            'prompt_tokens' => (int) $response->json('usage.prompt_tokens', 0),
            'completion_tokens' => (int) $response->json('usage.completion_tokens', 0),
            'latency_ms' => $latency,
        ]);

        return mb_substr($text, 0, (int) config('whatsapp.max_text_length'));
    }

    private function handoff(Conversation $conversation, Device $device, string $reason): void
    {
        $contact = $conversation->contact;

        if ($msg = trim((string) Setting::get('ai_handoff_message'))) {
            $this->messages->queueText($device, $contact->phone, $msg, options: ['contact' => $contact, 'ai' => true, 'source' => 'auto']);
        }

        $conversation->forceFill([
            'ai_paused_until' => now()->addHours(12),
            'status' => 'open',
            'priority' => in_array($conversation->priority, ['high', 'urgent'], true) ? $conversation->priority : 'high',
        ])->save();

        $conversation->notes()->create(['body' => 'AI menyerahkan ke CS: '.$reason]);

        AppNotification::notify('ai.handoff', $contact->displayName().' perlu dibantu CS', $reason, route('inbox.show', $conversation), $conversation->assigned_to ?? 'all');
    }

    public function dailyCount(): int
    {
        return AiLog::whereIn('purpose', ['reply', 'suggest'])->where('status', '!=', 'skipped')->whereNotNull('model')->where('created_at', '>=', now()->startOfDay())->count();
    }

    private function contactHourCount(Contact $contact): int
    {
        return Message::where('contact_id', $contact->id)->where('sent_by_ai', true)->where('created_at', '>=', now()->subHour())->count();
    }

    /** @return string[] */
    private function terms(string $text): array
    {
        preg_match_all('/[\p{L}\p{N}]{3,}/u', mb_strtolower($text), $m);

        return array_values(array_unique(array_diff($m[0], self::STOPWORDS)));
    }
}
