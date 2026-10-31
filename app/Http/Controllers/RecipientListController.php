<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\RecipientList;
use App\Models\Tag;
use App\Models\WaGroup;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * Rombongan penerima broadcast: kelompok kontak per peruntukan.
 * Anggota bisa ditambah dari tempel nomor, tag, anggota grup WA, atau aksi massal di Kontak.
 * @2026-Alifiandra
 */
class RecipientListController extends Controller
{
    public function index()
    {
        return view('lists.index', [
            'lists' => RecipientList::withCount([
                'contacts',
                'contacts as opted_in_count' => fn ($q) => $q->where('consent', 'opted_in'),
            ])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('lists.form', ['list' => new RecipientList]);
    }

    public function store(Request $request)
    {
        $list = RecipientList::create($this->validated($request) + ['created_by' => $request->user()->id]);
        AuditLog::record('list.create', 'Buat rombongan '.$list->name);

        return redirect()->route('lists.show', $list)->with('success', 'Rombongan dibuat. Sekarang tambahkan anggotanya.');
    }

    public function edit(RecipientList $list)
    {
        return view('lists.form', compact('list'));
    }

    public function update(Request $request, RecipientList $list)
    {
        $list->update($this->validated($request, $list));

        return redirect()->route('lists.show', $list)->with('success', 'Rombongan diperbarui.');
    }

    public function destroy(RecipientList $list)
    {
        AuditLog::record('list.delete', 'Hapus rombongan '.$list->name);
        $list->delete();

        return redirect()->route('lists.index')->with('success', 'Rombongan dihapus. Kontak tetap tersimpan.');
    }

    public function show(Request $request, RecipientList $list)
    {
        $q = trim((string) $request->query('q'));

        return view('lists.show', [
            'list' => $list,
            'members' => $list->contacts()->with('tags')
                ->when($q !== '', fn ($w) => $w->search($q))
                ->orderBy('name')->paginate(50)->withQueryString(),
            'counts' => [
                'total' => $list->contacts()->count(),
                'opted_in' => $list->contacts()->where('consent', 'opted_in')->count(),
                'opted_out' => $list->contacts()->where('consent', 'opted_out')->count(),
            ],
            'tags' => Tag::withCount('contacts')->orderBy('name')->get(),
            'groups' => WaGroup::orderBy('subject')->get(['id', 'subject', 'participants_count']),
            'otherLists' => RecipientList::whereKeyNot($list->id)->withCount('contacts')->orderBy('name')->get(),
        ]);
    }

    /** Tambah anggota: tempel nomor, dari tag, dari grup WA, atau dari rombongan lain. */
    public function addMembers(Request $request, RecipientList $list)
    {
        $data = $request->validate([
            'source' => ['required', 'in:numbers,tag,group,list'],
            'numbers' => ['nullable', 'required_if:source,numbers', 'string', 'max:100000'],
            'consent' => ['nullable', 'boolean'],
            'tag_id' => ['nullable', 'required_if:source,tag', 'integer', 'exists:tags,id'],
            'group_id' => ['nullable', 'required_if:source,group', 'integer', 'exists:wa_groups,id'],
            'list_id' => ['nullable', 'required_if:source,list', 'integer', 'exists:recipient_lists,id'],
        ], ['numbers.required_if' => 'Tempel minimal satu nomor.'], ['numbers' => 'daftar nomor']);

        $invalid = 0;
        $created = 0;

        $ids = match ($data['source']) {
            'tag' => Tag::find($data['tag_id'])->contacts()->pluck('contacts.id'),
            'list' => RecipientList::find($data['list_id'])->contacts()->pluck('contacts.id'),
            'group' => $this->contactsFromPhones(array_map(fn ($p) => ['phone' => $p, 'name' => null], WaGroup::find($data['group_id'])->memberPhones()), false, $request, $created, $invalid),
            'numbers' => $this->contactsFromPhones($this->parseNumbers($data['numbers']), $request->boolean('consent'), $request, $created, $invalid),
        };

        $added = $list->addContacts($ids);
        AuditLog::record('list.add', "Tambah {$added} anggota ke rombongan {$list->name}", ['source' => $data['source']]);

        $msg = "{$added} anggota baru ditambahkan".($created ? ", {$created} kontak baru dibuat" : '').($invalid ? ", {$invalid} nomor tidak valid dilewati" : '').'.';

        return back()->with('success', $msg);
    }

    public function removeMembers(Request $request, RecipientList $list)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['integer']]);
        $removed = $list->contacts()->detach($data['ids']);

        return back()->with('success', "{$removed} anggota dikeluarkan dari rombongan.");
    }

    /**
     * Tempel nomor, boleh dengan nama (satu orang per baris):
     *   081234567890, Budi Santoso   |   Budi Santoso - 0812 3456 7890   |   081234567890 (tanpa nama)
     * Baris tanpa huruf boleh berisi beberapa nomor dipisah koma / spasi.
     *
     * @return array<int, array{phone: string, name: ?string}>
     */
    public static function parseNumbers(string $raw): array
    {
        $out = [];

        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! preg_match('/\p{L}/u', $line)) {
                foreach (preg_split('/[,;\t]+|\s{2,}/', $line) ?: [] as $part) {
                    // "0812 3456 7890" tetap satu nomor; "0812…,0813…" dipisah koma
                    foreach (preg_match_all('/\+?\d[\d\s\-.()]{6,}\d/', $part, $m) ? $m[0] : [] as $num) {
                        $out[] = ['phone' => $num, 'name' => null];
                    }
                }

                continue;
            }

            if (preg_match('/\+?\d[\d\s\-.()]{6,}\d/', $line, $m)) {
                $name = trim(str_replace($m[0], ' ', $line), " \t,;:-|/");
                $name = preg_replace('/\s+/', ' ', $name);
                $out[] = ['phone' => $m[0], 'name' => $name !== '' ? mb_substr($name, 0, 150) : null];
            }
        }

        return $out;
    }

    private function contactsFromPhones(array $entries, bool $consent, Request $request, int &$created, int &$invalid)
    {
        $ids = collect();

        foreach ($entries as $entry) {
            $phone = PhoneNumber::normalize((string) $entry['phone']);

            if (! PhoneNumber::isValid($phone)) {
                $invalid++;

                continue;
            }

            $contact = Contact::findOrCreateByPhone($phone, ['source' => 'rombongan', 'name' => $entry['name'] ?? null]);
            $created += $contact->wasRecentlyCreated ? 1 : 0;

            // nama hanya diisi bila kontak belum punya nama (nama yang sudah ada tidak ditimpa)
            if (! $contact->name && ! empty($entry['name'])) {
                $contact->forceFill(['name' => $entry['name']])->save();
            }

            if ($consent && $contact->consent === 'unknown') {
                $contact->setConsent('opted_in', 'manual', 'Ditambahkan ke rombongan oleh '.$request->user()->name);
            }

            $ids->push($contact->id);
        }

        return $ids;
    }

    private function validated(Request $request, ?RecipientList $list = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('recipient_lists', 'name')->ignore($list?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ], [], ['name' => 'nama rombongan']);
    }
}
