<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Rombongan penerima: daftar kontak bernama untuk tujuan broadcast tertentu
 * (mis. "Reseller Jatim", "Pelanggan VIP", "Peserta Event").
 * @2026-Alifiandra
 */
class RecipientList extends Model
{
    protected $fillable = ['name', 'description', 'created_by'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_recipient_list')->withPivot('created_at');
    }

    /** Tambah kontak (tanpa duplikat). Mengembalikan jumlah anggota baru. */
    public function addContacts(iterable $contactIds): int
    {
        $ids = collect($contactIds)->map(fn ($id) => (int) $id)->filter()->unique();
        $existing = $this->contacts()->whereIn('contacts.id', $ids)->pluck('contacts.id');
        $new = $ids->diff($existing)->values();

        foreach ($new->chunk(500) as $chunk) {
            $this->contacts()->attach($chunk->mapWithKeys(fn ($id) => [$id => ['created_at' => now()]])->all());
        }

        return $new->count();
    }
}
