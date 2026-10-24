<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/** REST API /api/v1/templates @2026-Alifiandra */
class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(Template::with('variants')->where('is_active', true)->orderBy('name')->get()->map(fn (Template $t) => [
            'id' => $t->id,
            'name' => $t->name,
            'category' => $t->category,
            'variants' => $t->variants->map(fn ($v) => ['id' => $v->id, 'label' => $v->label, 'body' => $v->body])->values(),
        ])->values());
    }
}
