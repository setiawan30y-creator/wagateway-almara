<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Ticket;
use App\Services\TicketService;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** REST API /api/v1/tickets @2026-Alifiandra */
class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
            'overdue' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Ticket::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->latest('id')
            ->paginate($data['per_page'] ?? 20);

        return ApiResponse::success(collect($page->items())->map->toApiArray()->values(), 200, ['pagination' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function store(Request $request, TicketService $service): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'subject' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', Rule::in(array_keys(Ticket::CATEGORIES))],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
            'notify_customer' => ['nullable', 'boolean'],
        ]);

        $phone = PhoneNumber::normalize($data['phone']);

        if (! PhoneNumber::isValid($phone)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Request tidak valid.', 422, ['phone' => ['Nomor WhatsApp tidak valid.']]);
        }

        $contact = Contact::findOrCreateByPhone($phone, ['source' => 'api']);
        $ticket = $service->create($contact, $data, $request->attributes->get('api_key')?->user, (bool) ($data['notify_customer'] ?? false));

        return ApiResponse::success($ticket->toApiArray(), 201);
    }
}
