<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Device;
use App\Models\Tag;
use App\Models\Template;
use App\Services\CampaignService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** REST API /api/v1/campaigns @2026-Alifiandra */
class CampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $page = Campaign::latest('id')->paginate(20);

        return ApiResponse::success(collect($page->items())->map->toApiArray()->values(), 200, ['pagination' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = Campaign::find($id);

        return $campaign ? ApiResponse::success($campaign->toApiArray()) : ApiResponse::error('NOT_FOUND', 'Campaign tidak ditemukan.', 404);
    }

    public function store(Request $request, CampaignService $service): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'template_id' => ['required', 'integer', \App\Support\Tenancy::exists('templates')->where('is_active', true)],
            'device_id' => ['nullable', 'integer', \App\Support\Tenancy::exists('devices')->where('is_active', true)],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'stages' => ['nullable', 'array'],
            'stages.*' => [Rule::in(array_keys(Contact::STAGES))],
            'rate_per_minute' => ['nullable', 'integer', 'min:1', 'max:120'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'start' => ['nullable', 'boolean'],
        ]);

        $device = isset($data['device_id']) ? Device::find($data['device_id']) : Device::resolveDefault();

        if (! $device) {
            return ApiResponse::error('DEVICE_NOT_FOUND', 'Perangkat aktif tidak ditemukan.', 404);
        }

        $tagIds = Tag::whereIn('name', array_map('mb_strtolower', $data['tags'] ?? []))->pluck('id')->all();
        $apiKey = $request->attributes->get('api_key');

        $campaign = Campaign::create([
            'name' => $data['name'],
            'device_id' => $device->id,
            'template_id' => $data['template_id'],
            'audience' => ['tags' => $tagIds, 'stages' => $data['stages'] ?? []],
            'rate_per_minute' => $data['rate_per_minute'] ?? 20,
            'status' => isset($data['scheduled_at']) ? 'scheduled' : 'draft',
            'scheduled_at' => isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null,
            'created_by' => $apiKey?->user_id,
        ]);

        if (! isset($data['scheduled_at']) && ($data['start'] ?? false)) {
            $service->start($campaign);
        }

        return ApiResponse::success($campaign->refresh()->toApiArray(), 201, ['audience_preview' => $service->preview($campaign->audience)]);
    }
}
