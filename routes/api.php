<?php

use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\QrWebhookController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\CampaignController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\TemplateController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| ALMARA Gateway WA — REST API
| Dokumentasi: docs/api/openapi.yaml
| @2026-Alifiandra
*/

Route::prefix('v1')->middleware(['api.log', 'api.key', 'throttle:wa-api'])->group(function () {
    Route::post('/messages/send', [MessageController::class, 'send']);
    Route::post('/messages/media', [MessageController::class, 'media']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);

    Route::get('/contacts', [ContactController::class, 'index']);
    Route::post('/contacts', [ContactController::class, 'store']);

    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/templates', [TemplateController::class, 'index']);

    Route::get('/campaigns', [CampaignController::class, 'index']);
    Route::post('/campaigns', [CampaignController::class, 'store'])->middleware('feature:broadcast');
    Route::get('/campaigns/{id}', [CampaignController::class, 'show'])->whereNumber('id');

    Route::get('/webhooks', [WebhookController::class, 'index']);
    Route::post('/webhooks', [WebhookController::class, 'store']);
    Route::delete('/webhooks/{id}', [WebhookController::class, 'destroy'])->whereNumber('id');

    Route::get('/analytics', [AnalyticsController::class, 'index']);

    Route::get('/smart-links', [\App\Http\Controllers\Api\V1\SmartLinkController::class, 'index']);

    Route::get('/tickets', [\App\Http\Controllers\Api\V1\TicketController::class, 'index']);
    Route::post('/tickets', [\App\Http\Controllers\Api\V1\TicketController::class, 'store']);
});

// Webhook masuk dari provider (tanpa API key; diamankan token/signature)
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify']);
Route::post('/webhooks/meta', [MetaWebhookController::class, 'receive']);
Route::post('/webhooks/qr/{device}', QrWebhookController::class)->whereNumber('device');
