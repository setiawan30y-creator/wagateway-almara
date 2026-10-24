<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AutoReply;
use App\Models\Device;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Auto-reply kata kunci / sambutan / di luar jam kerja. @2026-Alifiandra */
class AutoReplyController extends Controller
{
    public function index()
    {
        return view('auto-replies.index', [
            'rules' => AutoReply::with(['device', 'tag'])->orderBy('priority')->orderBy('id')->get(),
            'working' => Setting::inWorkingHours(),
            'hours' => Setting::get('work_start').'–'.Setting::get('work_end'),
        ]);
    }

    public function create()
    {
        return view('auto-replies.form', ['rule' => new AutoReply(['trigger' => 'keyword', 'match_type' => 'contains', 'cooldown_minutes' => 60, 'priority' => 100, 'is_active' => true]), 'devices' => Device::orderBy('name')->get(), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $rule = AutoReply::create($this->validated($request));
        AuditLog::record('autoreply.create', 'Buat auto-reply '.$rule->name);

        return redirect()->route('auto-replies.index')->with('success', 'Auto-reply disimpan.');
    }

    public function edit(AutoReply $autoReply)
    {
        return view('auto-replies.form', ['rule' => $autoReply, 'devices' => Device::orderBy('name')->get(), 'tags' => Tag::orderBy('name')->get()]);
    }

    public function update(Request $request, AutoReply $autoReply)
    {
        $autoReply->update($this->validated($request));

        return redirect()->route('auto-replies.index')->with('success', 'Auto-reply diperbarui.');
    }

    public function toggle(AutoReply $autoReply)
    {
        $autoReply->update(['is_active' => ! $autoReply->is_active]);

        return back();
    }

    public function destroy(AutoReply $autoReply)
    {
        AuditLog::record('autoreply.delete', 'Hapus auto-reply '.$autoReply->name);
        $autoReply->delete();

        return back()->with('success', 'Auto-reply dihapus.');
    }

    /** Uji: pesan contoh cocok dengan aturan mana? */
    public function test(Request $request)
    {
        $text = (string) $request->input('text');
        $rule = AutoReply::where('is_active', true)->where('trigger', 'keyword')->orderBy('priority')->orderBy('id')->get()->first(fn ($r) => $r->matches($text));

        return response()->json(['matched' => (bool) $rule, 'rule' => $rule?->name, 'reply' => $rule?->reply]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'trigger' => ['required', Rule::in(array_keys(AutoReply::TRIGGERS))],
            'match_type' => ['required', Rule::in(array_keys(AutoReply::MATCHES))],
            'keywords' => ['nullable', 'required_if:trigger,keyword', 'string', 'max:500'],
            'reply' => ['required', 'string', 'max:'.config('whatsapp.max_text_length')],
            'device_id' => ['nullable', 'integer', 'exists:devices,id'],
            'add_tag_id' => ['nullable', 'integer', 'exists:tags,id'],
            'cooldown_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
        ], ['keywords.required_if' => 'Isi minimal satu kata kunci.'], ['reply' => 'balasan']);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
