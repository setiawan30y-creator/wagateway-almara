<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\SendController;
use Illuminate\Support\Facades\Route;

/*
| ALMARA Gateway WA — Web Admin (PWA)
| @2026-Alifiandra
*/

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/kirim', [SendController::class, 'create'])->name('send.create');
    Route::post('/kirim', [SendController::class, 'store'])->name('send.store');

    Route::get('/pesan', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/pesan/export', [MessageController::class, 'export'])->name('messages.export');
    Route::get('/pesan/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/pesan/{message}/ulang', [MessageController::class, 'retry'])->name('messages.retry');

    Route::get('/perangkat', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/perangkat/tambah', [DeviceController::class, 'create'])->name('devices.create');
    Route::post('/perangkat', [DeviceController::class, 'store'])->name('devices.store');
    Route::get('/perangkat/{device}/ubah', [DeviceController::class, 'edit'])->name('devices.edit');
    Route::put('/perangkat/{device}', [DeviceController::class, 'update'])->name('devices.update');
    Route::delete('/perangkat/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
    Route::post('/perangkat/{device}/utama', [DeviceController::class, 'makeDefault'])->name('devices.default');
    Route::post('/perangkat/{device}/cek', [DeviceController::class, 'check'])->name('devices.check');
    Route::get('/perangkat/{device}/pairing', [DeviceController::class, 'pair'])->name('devices.pair');
    Route::get('/perangkat/{device}/qr', [DeviceController::class, 'qr'])->name('devices.qr');

    Route::get('/api-key', [ApiKeyController::class, 'index'])->name('api-keys.index');
    Route::post('/api-key', [ApiKeyController::class, 'store'])->name('api-keys.store');
    Route::delete('/api-key/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('/akun', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/akun', [AccountController::class, 'update'])->name('account.update');
});
