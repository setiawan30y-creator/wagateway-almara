<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Kontak & CRM dasar: daftar, pipeline, detail, import/export CSV, consent.
 * @2026-Alifiandra
 */
class ContactController extends Controller
{
    public function index(Request $request)
    {
        $view = $request->query('view') === 'pipeline' ? 'pipeline' : 'list';

        $query = Contact::with(['tags', 'assignee'])
            ->search($request->query('q'))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tag)))
            ->when($request->query('stage'), fn ($q, $s) => $q->where('stage', $s))
            ->when($request->query('consent'), fn ($q, $c) => $q->where('consent', $c));

        if ($view === 'pipeline') {
            $columns = collect(Contact::STAGES)->map(fn ($label, $stage) => [
                'label' => $label,
                'total' => (clone $query)->where('stage', $stage)->count(),
                'items' => (clone $query)->where('stage', $stage)->orderByDesc('last_message_at')->orderByDesc('id')->limit(30)->get(),
            ]);

            return view('contacts.pipeline', ['columns' => $columns, 'tags' => Tag::orderBy('name')->get()]);
        }

        return view('contacts.index', [
            'contacts' => $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(50)->withQueryString(),
            'tags' => Tag::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('contacts.form', ['contact' => new Contact(), 'agents' => $this->agents()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $contact = Contact::create($data['attributes'] + ['source' => 'manual']);
        $contact->tags()->sync($data['tag_ids']);
        $this->applyConsent($contact, $data['consent'], 'manual');

        AuditLog::record('contact.create', 'Tambah kontak '.$contact->phone);

        return redirect()->route('contacts.show', $contact)->with('success', 'Kontak ditambahkan.');
    }

    public function show(Contact $contact)
    {
        $contact->load(['tags', 'assignee', 'conversation', 'consentEvents.user']);

        return view('contacts.show', [
            'contact' => $contact,
            'messages' => $contact->messages()->latest('id')->limit(30)->get(),
            'stats' => [
                'in' => $contact->messages()->where('direction', 'in')->count(),
                'out' => $contact->messages()->where('direction', 'out')->count(),
                'campaigns' => $contact->messages()->whereNotNull('campaign_id')->distinct()->count('campaign_id'),
            ],
        ]);
    }

    public function edit(Contact $contact)
    {
        return view('contacts.form', ['contact' => $contact->load('tags'), 'agents' => $this->agents()]);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $this->validated($request, $contact);
        $contact->update($data['attributes']);
        $contact->tags()->sync($data['tag_ids']);
        $this->applyConsent($contact, $data['consent'], 'manual');

        return redirect()->route('contacts.show', $contact)->with('success', 'Kontak diperbarui.');
    }

    public function destroy(Contact $contact)
    {
        AuditLog::record('contact.delete', 'Hapus kontak '.$contact->phone);
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Kontak dihapus.');
    }

    public function stage(Request $request, Contact $contact)
    {
        $data = $request->validate(['stage' => ['required', Rule::in(array_keys(Contact::STAGES))]]);
        $contact->update(['stage' => $data['stage']]);

        return back();
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['tag', 'untag', 'list', 'opt_in', 'opt_out', 'stage', 'delete'])],
            'value' => ['nullable', 'string', 'max:100'],
        ]);

        $contacts = Contact::whereIn('id', $data['ids'])->get();
        $value = (string) ($data['value'] ?? '');

        if ($data['action'] === 'list') {
            abort_if(trim($value) === '', 422, 'Isi nama rombongan.');
            $list = \App\Models\RecipientList::firstOrCreate(['name' => trim($value)], ['created_by' => $request->user()->id]);
            $added = $list->addContacts($contacts->pluck('id'));
            AuditLog::record('list.add', "Tambah {$added} kontak ke rombongan {$list->name}");

            return back()->with('success', "{$added} kontak dimasukkan ke rombongan \"{$list->name}\".");
        }

        foreach ($contacts as $contact) {
            match ($data['action']) {
                'tag' => $contact->tags()->syncWithoutDetaching(Tag::idsFromString($value)),
                'untag' => $contact->tags()->detach(Tag::whereIn('name', array_map('trim', explode(',', mb_strtolower($value))))->pluck('id')),
                'opt_in' => $contact->setConsent('opted_in', 'manual', 'Aksi massal'),
                'opt_out' => $contact->setConsent('opted_out', 'manual', 'Aksi massal'),
                'stage' => array_key_exists($value, Contact::STAGES) ? $contact->update(['stage' => $value]) : null,
                'delete' => $request->user()->isAdmin() ? $contact->delete() : null,
            };
        }

        AuditLog::record('contact.bulk', "Aksi massal {$data['action']} untuk ".$contacts->count().' kontak');

        return back()->with('success', 'Aksi diterapkan ke '.$contacts->count().' kontak.');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'consent' => ['nullable', 'boolean'],
        ], [], ['file' => 'file CSV']);

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        $first = fgets($handle);
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : ',';
        rewind($handle);

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF\"")), (array) fgetcsv($handle, 0, $delimiter));
        $col = fn (array $names) => collect($names)->map(fn ($n) => array_search($n, $header, true))->first(fn ($i) => $i !== false);

        $iPhone = $col(['phone', 'nomor', 'whatsapp', 'no_hp', 'hp', 'telepon']);
        $iName = $col(['name', 'nama']);
        $iEmail = $col(['email']);
        $iTags = $col(['tags', 'tag', 'label']);

        if ($iPhone === null) {
            fclose($handle);

            throw ValidationException::withMessages(['file' => 'Kolom nomor tidak ditemukan. Gunakan header: name, phone, email, tags.']);
        }

        $created = $updated = $invalid = 0;
        $withConsent = $request->boolean('consent');

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $phone = PhoneNumber::normalize($row[$iPhone] ?? '');

            if (! PhoneNumber::isValid($phone)) {
                $invalid++;

                continue;
            }

            $contact = Contact::firstOrNew(['phone' => $phone]);
            $contact->exists ? $updated++ : $created++;
            $contact->fill(array_filter([
                'name' => $iName !== null ? trim((string) ($row[$iName] ?? '')) : null,
                'email' => $iEmail !== null ? trim((string) ($row[$iEmail] ?? '')) : null,
            ]));
            $contact->source ??= 'import';
            $contact->save();

            if ($iTags !== null && ! empty($row[$iTags])) {
                $contact->tags()->syncWithoutDetaching(Tag::idsFromString($row[$iTags]));
            }

            if ($withConsent && $contact->consent === 'unknown') {
                $contact->setConsent('opted_in', 'import', 'Import CSV (dikonfirmasi pengguna)');
            }
        }

        fclose($handle);
        AuditLog::record('contact.import', "Import kontak: {$created} baru, {$updated} diperbarui, {$invalid} tidak valid");

        return redirect()->route('contacts.index')->with('success', "Import selesai: {$created} baru, {$updated} diperbarui, {$invalid} nomor tidak valid.");
    }

    public function export(Request $request)
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'phone', 'email', 'stage', 'consent', 'tags', 'source', 'last_message_at', 'created_at']);

            Contact::with('tags')->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [$c->name, $c->phone, $c->email, $c->stage, $c->consent, $c->tags->pluck('name')->implode(','), $c->source, $c->last_message_at, $c->created_at]);
                }
            });

            fclose($out);
        }, 'kontak-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'regex:/^[1-9]\d{7,14}$/', Rule::unique('contacts', 'phone')->ignore($contact?->id)],
            'email' => ['nullable', 'email', 'max:191'],
            'stage' => ['required', Rule::in(array_keys(Contact::STAGES))],
            'consent' => ['required', Rule::in(array_keys(Contact::CONSENTS))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'tags' => ['nullable', 'string', 'max:500'],
        ], ['phone.regex' => 'Nomor WhatsApp tidak valid.', 'phone.unique' => 'Nomor ini sudah terdaftar.'], ['phone' => 'nomor WhatsApp']);

        return [
            'attributes' => collect($data)->only(['name', 'phone', 'email', 'stage', 'assigned_to', 'notes'])->all(),
            'tag_ids' => Tag::idsFromString($data['tags'] ?? ''),
            'consent' => $data['consent'],
        ];
    }

    private function applyConsent(Contact $contact, string $consent, string $source): void
    {
        $contact->setConsent($consent, $source);
    }

    private function agents()
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
