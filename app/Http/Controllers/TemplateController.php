<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Smart Template: template + varian A/B/C + variabel personalisasi.
 * @2026-Alifiandra
 */
class TemplateController extends Controller
{
    public function index()
    {
        $templates = Template::with('variants')->withCount('variants')->orderBy('name')->get();

        // Analitik varian: terkirim / dibalas per varian dari campaign
        $variantStats = DB::table('campaign_recipients as r')
            ->join('messages as m', 'm.id', '=', 'r.message_id')
            ->whereNotNull('r.template_variant_id')
            ->selectRaw("r.template_variant_id as id, count(*) as total, sum(case when m.status in ('sent','delivered','read') then 1 else 0 end) as sent, sum(case when m.status = 'read' then 1 else 0 end) as read_count, sum(case when m.status = 'failed' then 1 else 0 end) as failed")
            ->groupBy('r.template_variant_id')
            ->get()
            ->keyBy('id');

        return view('templates.index', compact('templates', 'variantStats'));
    }

    public function create()
    {
        return view('templates.form', ['template' => new Template(['category' => 'marketing', 'is_active' => true]), 'variants' => ['A' => '', 'B' => '', 'C' => '']]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $template = DB::transaction(function () use ($data, $request) {
            $template = Template::create($data['attributes'] + ['created_by' => $request->user()->id]);
            $this->syncVariants($template, $data['variants']);

            return $template;
        });

        AuditLog::record('template.create', 'Buat template '.$template->name);

        return redirect()->route('templates.index')->with('success', 'Template disimpan.');
    }

    public function edit(Template $template)
    {
        $variants = ['A' => '', 'B' => '', 'C' => ''];
        foreach ($template->variants as $v) {
            $variants[$v->label] = $v->body;
        }

        return view('templates.form', compact('template', 'variants'));
    }

    public function update(Request $request, Template $template)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($template, $data) {
            $template->update($data['attributes']);
            $this->syncVariants($template, $data['variants']);
        });

        AuditLog::record('template.update', 'Ubah template '.$template->name);

        return redirect()->route('templates.index')->with('success', 'Template diperbarui.');
    }

    public function destroy(Template $template)
    {
        AuditLog::record('template.delete', 'Hapus template '.$template->name);
        $template->delete();

        return redirect()->route('templates.index')->with('success', 'Template dihapus.');
    }

    public function preview(Request $request)
    {
        $body = (string) $request->input('body', '');
        $contact = Contact::whereNotNull('name')->first() ?? new Contact(['name' => 'Nama Pelanggan', 'phone' => '6281200000000', 'email' => 'email@contoh.com']);

        return response()->json(['text' => Template::render($body, $contact)]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(array_keys(Template::CATEGORIES))],
            'is_active' => ['nullable', 'boolean'],
            'variants' => ['required', 'array'],
            'variants.A' => ['required', 'string', 'max:'.config('whatsapp.max_text_length')],
            'variants.B' => ['nullable', 'string', 'max:'.config('whatsapp.max_text_length')],
            'variants.C' => ['nullable', 'string', 'max:'.config('whatsapp.max_text_length')],
        ], [], ['variants.A' => 'isi varian A', 'name' => 'nama template']);

        return [
            'attributes' => ['name' => $data['name'], 'category' => $data['category'], 'is_active' => $request->boolean('is_active')],
            'variants' => array_filter($data['variants'], fn ($v) => trim((string) $v) !== ''),
        ];
    }

    private function syncVariants(Template $template, array $variants): void
    {
        foreach (['A', 'B', 'C'] as $label) {
            $existing = $template->variants()->where('label', $label)->first();

            if (isset($variants[$label])) {
                $existing ? $existing->update(['body' => $variants[$label]]) : $template->variants()->create(['label' => $label, 'body' => $variants[$label]]);
            } elseif ($existing) {
                $existing->delete();
            }
        }
    }
}
