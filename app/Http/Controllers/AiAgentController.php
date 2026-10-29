<?php

namespace App\Http\Controllers;

use App\AI\AiAgent;
use App\Models\AiLog;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\KnowledgeItem;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Menu AI Agent: pengaturan, basis pengetahuan, uji coba, pemakaian.
 * @2026-Alifiandra
 */
class AiAgentController extends Controller
{
    public const MODELS = [
        'openrouter/free' => 'openrouter/free — otomatis pilih model gratis yang tersedia (disarankan)',
        'google/gemma-4-31b-it:free' => 'Gemma 4 31B (gratis)',
        'qwen/qwen3.8-27b:free' => 'Qwen 3.8 27B (gratis)',
        'nvidia/nemotron-3-super-120b-a12b:free' => 'Nemotron 3 Super 120B (gratis)',
        'nvidia/nemotron-3.5-lightning:free' => 'Nemotron 3.5 Lightning (gratis, cepat)',
    ];

    public function index(AiAgent $agent)
    {
        $keys = ['ai_enabled', 'ai_base_url', 'ai_model', 'ai_agent_name', 'ai_persona', 'ai_rules', 'ai_mode', 'ai_handoff_keywords', 'ai_handoff_message', 'ai_pause_minutes', 'ai_max_per_contact_hour', 'ai_daily_limit', 'ai_temperature'];

        $today = AiLog::where('created_at', '>=', now()->startOfDay());
        $week = AiLog::where('created_at', '>=', now()->subDays(6)->startOfDay());

        return view('ai.index', [
            's' => collect($keys)->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all(),
            'hasKey' => Setting::secret('ai_api_key') !== '',
            'configured' => $agent->isConfigured(),
            'models' => self::MODELS,
            'knowledge' => KnowledgeItem::orderBy('category')->orderBy('title')->get(),
            'stats' => [
                'today' => (clone $today)->whereNotNull('model')->count(),
                'replies_today' => (clone $today)->where('purpose', 'reply')->where('status', 'ok')->count(),
                'handoff_week' => (clone $week)->where('status', 'handoff')->count(),
                'errors_week' => (clone $week)->where('status', 'error')->count(),
                'tokens_week' => (int) (clone $week)->sum(DB::raw('prompt_tokens + completion_tokens')),
                'avg_ms' => (int) (clone $week)->where('status', 'ok')->avg('latency_ms'),
            ],
            'logs' => AiLog::latest('id')->limit(15)->get(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ai_base_url' => ['required', 'url', 'max:255'],
            'ai_api_key' => ['nullable', 'string', 'max:255'],
            'ai_model' => ['required', 'string', 'max:150'],
            'ai_agent_name' => ['required', 'string', 'max:60'],
            'ai_persona' => ['required', 'string', 'max:2000'],
            'ai_rules' => ['nullable', 'string', 'max:3000'],
            'ai_mode' => ['required', Rule::in(['always', 'outside_hours', 'unassigned'])],
            'ai_handoff_keywords' => ['nullable', 'string', 'max:500'],
            'ai_handoff_message' => ['nullable', 'string', 'max:500'],
            'ai_pause_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'ai_max_per_contact_hour' => ['required', 'integer', 'min:1', 'max:100'],
            'ai_daily_limit' => ['required', 'integer', 'min:1', 'max:100000'],
            'ai_temperature' => ['required', 'numeric', 'min:0', 'max:1.5'],
        ], [], ['ai_base_url' => 'URL API', 'ai_model' => 'model', 'ai_persona' => 'persona']);

        if (! empty($data['ai_api_key'])) {
            Setting::setSecret('ai_api_key', trim($data['ai_api_key']));
        }
        unset($data['ai_api_key']);

        $data['ai_enabled'] = $request->boolean('ai_enabled') ? '1' : '0';

        foreach ($data as $k => $v) {
            Setting::set($k, (string) $v);
        }

        AuditLog::record('ai.settings', 'Ubah pengaturan AI Agent ('.($data['ai_enabled'] === '1' ? 'aktif' : 'nonaktif').')');

        if ($data['ai_enabled'] === '1' && Setting::secret('ai_api_key') === '') {
            return back()->with('warning', 'Pengaturan disimpan, tetapi AI belum bisa aktif: isi API key OpenRouter.');
        }

        return back()->with('success', 'Pengaturan AI disimpan.');
    }

    public function forgetKey()
    {
        Setting::setSecret('ai_api_key', '');
        Setting::set('ai_enabled', '0');
        AuditLog::record('ai.key_removed', 'Hapus API key AI');

        return back()->with('success', 'API key dihapus dan AI dinonaktifkan.');
    }

    public function storeKnowledge(Request $request)
    {
        KnowledgeItem::create($this->knowledgeData($request));

        return redirect()->to(route('ai.index').'#knowledge')->with('success', 'Pengetahuan ditambahkan.');
    }

    public function updateKnowledge(Request $request, KnowledgeItem $item)
    {
        $item->update($this->knowledgeData($request));

        return redirect()->to(route('ai.index').'#knowledge')->with('success', 'Pengetahuan diperbarui.');
    }

    public function destroyKnowledge(KnowledgeItem $item)
    {
        $item->delete();

        return redirect()->to(route('ai.index').'#knowledge')->with('success', 'Pengetahuan dihapus.');
    }

    /** Uji chat dengan AI (tanpa WhatsApp). */
    public function test(Request $request, AiAgent $agent)
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'history' => ['nullable', 'array', 'max:20'],
        ]);

        if (Setting::secret('ai_api_key') === '') {
            return response()->json(['ok' => false, 'answer' => 'Isi API key OpenRouter terlebih dahulu, lalu simpan.'], 422);
        }

        try {
            $answer = $agent->test($data['question'], $data['history'] ?? []);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'answer' => $e->getMessage()], 502);
        }

        $handoff = str_contains($answer, AiAgent::HANDOFF);

        return response()->json([
            'ok' => true,
            'handoff' => $handoff,
            'answer' => $handoff ? '→ (diserahkan ke CS) '.Setting::get('ai_handoff_message') : $answer,
        ]);
    }

    /** Copilot: saran balasan di inbox (boleh dipakai agent). */
    public function suggest(Conversation $conversation, AiAgent $agent)
    {
        if (Setting::secret('ai_api_key') === '') {
            return response()->json(['ok' => false, 'text' => 'AI belum diatur. Minta admin mengisi API key di menu AI Agent.'], 422);
        }

        try {
            return response()->json(['ok' => true, 'text' => $agent->suggest($conversation)]);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'text' => $e->getMessage()], 502);
        }
    }

    /** Aktif/nonaktifkan AI untuk satu percakapan (boleh dipakai agent). */
    public function toggleConversation(Request $request, Conversation $conversation)
    {
        $enable = $request->boolean('enable');
        $conversation->forceFill(['ai_enabled' => $enable, 'ai_paused_until' => $enable ? null : $conversation->ai_paused_until])->save();

        return back()->with('success', $enable ? 'AI aktif kembali di percakapan ini.' : 'AI dimatikan untuk percakapan ini.');
    }

    private function knowledgeData(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'content' => ['required', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:50'],
        ], [], ['title' => 'judul', 'content' => 'isi']);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
