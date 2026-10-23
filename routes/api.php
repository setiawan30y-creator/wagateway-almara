<?php

use App\Http\Controllers\Api\MetaWebhookController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

/*
| ALMARA Gateway WA — REST API
| Dokumentasi: docs/api/openapi.yaml
| @2026-Alifiandra
*/

Route::prefix('v1')->middleware(['api.key', 'throttle:wa-api'])->group(function () {
    Route::post('/messages/send', [MessageController::class, 'send']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{id}', [MessageController::class, 'show']);
    Route::get('/devices', [DeviceController::class, 'index']);
});

// Webhook Meta Cloud API (tanpa API key; diamankan verify token + signature)
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify']);
Route::post('/webhooks/meta', [MetaWebhookController::class, 'receive']);
