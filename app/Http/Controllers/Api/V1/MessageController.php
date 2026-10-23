<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Device;
use App\Models\Message;
use App\Services\MessageService;
use App\Support\ApiResponse;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * REST API /api/v1/messages
 * @2026-Alifiandra
 */
class MessageController extends Controller
{
    public function send(Request $request, MessageService $service): JsonResponse
    {
        /** @var ApiKey $apiKey */
        $apiKey = $request->attributes->get('api_key');

        $data = $request->validate([
            'to' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:'.config('whatsapp.max_text_length')],
            'device_id' => ['nullable', 'integer'],
        ], [
            'to.required' => 'Field tujuan wajib diisi.',
            'message.required' => 'Isi pesan wajib diisi.',
        ]);

        $to = PhoneNumber::normalize($data['to']);

        if (! PhoneNumber::isValid($to)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Request tidak valid.', 422, ['to' => ['Nomor WhatsApp tidak valid.']]);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey !== null) {
            $idempotencyKey = mb_substr(trim($idempotencyKey), 0, 100);

            $existing = Message::where('api_key_id', $apiKey->id)->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                if ($existing->to !== $to || $existing->body !== $data['message']) {
                    return ApiResponse::error('IDEMPOTENCY_CONFLICT', 'Idempotency-Key sudah dipakai untuk request berbeda.', 409);
                }

                return ApiResponse::success($existing->toApiArray(), 200, [], ['Idempotent-Replayed' => 'true']);
            }
        }

        $device = isset($data['device_id'])
            ? Device::where('is_active', true)->find($data['device_id'])
            : Device::resolveDefault();

        if (! $device) {
            return ApiResponse::error('DEVICE_NOT_FOUND', 'Perangkat aktif tidak ditemukan. Tambahkan perangkat di menu Perangkat.', 404);
        }

        $message = $service->queueText($device, $to, $data['message'], $apiKey->user, $apiKey, $idempotencyKey);

        return ApiResponse::success($message->toApiArray(), 202);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(Message::STATUSES)],
            'to' => ['nullable', 'string', 'max:30'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = Message::query()
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['to'] ?? null, fn ($q, $t) => $q->where('to', PhoneNumber::normalize($t)))
            ->latest('id')
            ->paginate($data['per_page'] ?? 20);

        return ApiResponse::success(
            collect($page->items())->map->toApiArray()->values(),
            200,
            ['pagination' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ]],
        );
    }

    public function show(string $id): JsonResponse
    {
        $message = Message::where('public_id', $id)->first();

        if (! $message) {
            return ApiResponse::error('NOT_FOUND', 'Pesan tidak ditemukan.', 404);
        }

        return ApiResponse::success($message->toApiArray());
    }
}
