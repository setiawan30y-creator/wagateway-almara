<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Tag;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** REST API /api/v1/contacts @2026-Alifiandra */
class ContactController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tag' => ['nullable', 'string', 'max:50'],
            'stage' => ['nullable', Rule::in(array_keys(Contact::STAGES))],
            'consent' => ['nullable', Rule::in(array_keys(Contact::CONSENTS))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Contact::with('tags')
            ->search($data['q'] ?? null)
            ->when($data['tag'] ?? null, fn ($q, $t) => $q->whereHas('tags', fn ($w) => $w->where('name', mb_strtolower($t))))
            ->when($data['stage'] ?? null, fn ($q, $s) => $q->where('stage', $s))
            ->when($data['consent'] ?? null, fn ($q, $c) => $q->where('consent', $c))
            ->orderByDesc('id')
            ->paginate($data['per_page'] ?? 20);

        return ApiResponse::success(collect($page->items())->map->toApiArray()->values(), 200, ['pagination' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    /** Buat atau perbarui kontak berdasarkan nomor (upsert). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:191'],
            'stage' => ['nullable', Rule::in(array_keys(Contact::STAGES))],
            'consent' => ['nullable', Rule::in(['opted_in', 'opted_out'])],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:50'],
        ]);

        $phone = PhoneNumber::normalize($data['phone']);

        if (! PhoneNumber::isValid($phone)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Request tidak valid.', 422, ['phone' => ['Nomor WhatsApp tidak valid.']]);
        }

        $contact = Contact::firstOrNew(['phone' => $phone]);
        $created = ! $contact->exists;
        $contact->fill(array_filter(collect($data)->only(['name', 'email', 'stage'])->all(), fn ($v) => $v !== null));
        $contact->source ??= 'api';
        $contact->save();

        if (! empty($data['tags'])) {
            $contact->tags()->syncWithoutDetaching(Tag::idsFromString(implode(',', $data['tags'])));
        }

        if (! empty($data['consent'])) {
            $contact->setConsent($data['consent'], 'api');
        }

        return ApiResponse::success($contact->refresh()->load('tags')->toApiArray(), $created ? 201 : 200);
    }
}
