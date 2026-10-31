<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ApiKeyController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SendController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
| ALMARA Gateway WA — Web Admin (PWA)
| Role: owner/admin = semua menu; agent = Inbox, Kontak, Kirim, Riwayat.
| @2026-Alifiandra
*/

Route::redirect('/', '/dashboard');

// Smart Link publik (tanpa login)
Route::get('/l/{slug}', [\App\Http\Controllers\SmartLinkController::class, 'go'])->where('slug', '[A-Za-z0-9-]+')->name('smart-links.go')->middleware('throttle:120,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Inbox
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/poll', [InboxController::class, 'poll'])->name('inbox.poll');
    Route::post('/inbox/mulai', [InboxController::class, 'start'])->name('inbox.start');
    Route::post('/inbox/simulasi', [InboxController::class, 'simulate'])->name('inbox.simulate');
    Route::get('/inbox/{conversation}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{conversation}/balas', [InboxController::class, 'reply'])->name('inbox.reply');
    Route::post('/inbox/{conversation}/catatan', [InboxController::class, 'note'])->name('inbox.note');
    Route::patch('/inbox/{conversation}', [InboxController::class, 'update'])->name('inbox.update');

    // Kontak & CRM
    // Rombongan penerima broadcast
    Route::get('/rombongan', [\App\Http\Controllers\RecipientListController::class, 'index'])->name('lists.index');
    Route::get('/rombongan/buat', [\App\Http\Controllers\RecipientListController::class, 'create'])->name('lists.create');
    Route::post('/rombongan', [\App\Http\Controllers\RecipientListController::class, 'store'])->name('lists.store');
    Route::get('/rombongan/{list}', [\App\Http\Controllers\RecipientListController::class, 'show'])->name('lists.show');
    Route::get('/rombongan/{list}/ubah', [\App\Http\Controllers\RecipientListController::class, 'edit'])->name('lists.edit');
    Route::put('/rombongan/{list}', [\App\Http\Controllers\RecipientListController::class, 'update'])->name('lists.update');
    Route::delete('/rombongan/{list}', [\App\Http\Controllers\RecipientListController::class, 'destroy'])->name('lists.destroy');
    Route::post('/rombongan/{list}/anggota', [\App\Http\Controllers\RecipientListController::class, 'addMembers'])->name('lists.add');
    Route::post('/rombongan/{list}/keluarkan', [\App\Http\Controllers\RecipientListController::class, 'removeMembers'])->name('lists.remove');

    // Grup WhatsApp & sinkron kontak HP (perangkat Scan QR)
    Route::get('/grup', [\App\Http\Controllers\GroupController::class, 'index'])->name('groups.index');
    Route::post('/grup/sinkron', [\App\Http\Controllers\GroupController::class, 'sync'])->name('groups.sync');
    Route::post('/kontak/sinkron-hp', [\App\Http\Controllers\GroupController::class, 'syncContacts'])->name('contacts.sync');
    Route::get('/grup/{group}', [\App\Http\Controllers\GroupController::class, 'show'])->name('groups.show');
    Route::get('/grup/{group}/chat', [\App\Http\Controllers\GroupController::class, 'chat'])->name('groups.chat');
    Route::post('/grup/{group}/kirim', [\App\Http\Controllers\GroupController::class, 'send'])->name('groups.send');
    Route::post('/grup/{group}/impor', [\App\Http\Controllers\GroupController::class, 'importMembers'])->name('groups.import');

    Route::get('/kontak', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/kontak/tambah', [ContactController::class, 'create'])->name('contacts.create');
    Route::get('/kontak/export', [ContactController::class, 'export'])->name('contacts.export');
    Route::post('/kontak/import', [ContactController::class, 'import'])->name('contacts.import');
    Route::post('/kontak/massal', [ContactController::class, 'bulk'])->name('contacts.bulk');
    Route::post('/kontak', [ContactController::class, 'store'])->name('contacts.store');
    Route::get('/kontak/{contact}', [ContactController::class, 'show'])->name('contacts.show');
    Route::get('/kontak/{contact}/ubah', [ContactController::class, 'edit'])->name('contacts.edit');
    Route::put('/kontak/{contact}', [ContactController::class, 'update'])->name('contacts.update');
    Route::patch('/kontak/{contact}/tahap', [ContactController::class, 'stage'])->name('contacts.stage');
    Route::delete('/kontak/{contact}', [ContactController::class, 'destroy'])->name('contacts.destroy')->middleware('can:admin');

    // AI untuk agent: saran balasan & on/off per percakapan
    Route::post('/inbox/{conversation}/ai-saran', [\App\Http\Controllers\AiAgentController::class, 'suggest'])->name('ai.suggest');
    Route::post('/inbox/{conversation}/ai', [\App\Http\Controllers\AiAgentController::class, 'toggleConversation'])->name('ai.conversation');

    // Tiket
    Route::get('/tiket', [\App\Http\Controllers\TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tiket/buat', [\App\Http\Controllers\TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tiket', [\App\Http\Controllers\TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tiket/{ticket}', [\App\Http\Controllers\TicketController::class, 'show'])->name('tickets.show');
    Route::patch('/tiket/{ticket}', [\App\Http\Controllers\TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tiket/{ticket}/catatan', [\App\Http\Controllers\TicketController::class, 'note'])->name('tickets.note');

    // Kirim & riwayat
    Route::get('/kirim', [SendController::class, 'create'])->name('send.create');
    Route::post('/kirim', [SendController::class, 'store'])->name('send.store');
    Route::get('/pesan', [MessageController::class, 'index'])->name('messages.index');
    Route::get('/pesan/export', [MessageController::class, 'export'])->name('messages.export');
    Route::get('/pesan/{message}', [MessageController::class, 'show'])->name('messages.show');
    Route::post('/pesan/{message}/ulang', [MessageController::class, 'retry'])->name('messages.retry');

    Route::get('/media/{message}', [\App\Http\Controllers\MediaController::class, 'show'])->name('media.show');

    // Panduan / dokumentasi
    Route::get('/panduan/{slug?}', [\App\Http\Controllers\DocsController::class, 'show'])->name('docs.show');

    // Notifikasi & akun
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.readAll');
    Route::get('/notifikasi/{notification}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::get('/akun', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('/akun', [AccountController::class, 'update'])->name('account.update');

    // Khusus owner/admin
    Route::middleware('can:admin')->group(function () {
        Route::get('/template', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('/template/tambah', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('/template/pratinjau', [TemplateController::class, 'preview'])->name('templates.preview');
        Route::post('/template', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('/template/{template}/ubah', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/template/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::delete('/template/{template}', [TemplateController::class, 'destroy'])->name('templates.destroy');

        Route::get('/broadcast', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/broadcast/buat', [CampaignController::class, 'create'])->name('campaigns.create');
        Route::post('/broadcast/pratinjau', [CampaignController::class, 'preview'])->name('campaigns.preview');
        Route::post('/broadcast', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/broadcast/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/template/{template}/lampiran', [\App\Http\Controllers\MediaController::class, 'template'])->name('templates.media');
        Route::get('/broadcast/{campaign}/lampiran', [\App\Http\Controllers\MediaController::class, 'campaign'])->name('campaigns.media');
        Route::post('/broadcast/{campaign}/lampiran', [CampaignController::class, 'attachment'])->name('campaigns.attachment');
        Route::post('/broadcast/{campaign}/{action}', [CampaignController::class, 'action'])->whereIn('action', ['start', 'pause', 'resume', 'cancel', 'run'])->name('campaigns.action');
        Route::delete('/broadcast/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

        Route::get('/smart-link', [\App\Http\Controllers\SmartLinkController::class, 'index'])->name('smart-links.index');
        Route::get('/smart-link/buat', [\App\Http\Controllers\SmartLinkController::class, 'create'])->name('smart-links.create');
        Route::post('/smart-link', [\App\Http\Controllers\SmartLinkController::class, 'store'])->name('smart-links.store');
        Route::get('/smart-link/{smartLink}', [\App\Http\Controllers\SmartLinkController::class, 'show'])->name('smart-links.show');
        Route::get('/smart-link/{smartLink}/ubah', [\App\Http\Controllers\SmartLinkController::class, 'edit'])->name('smart-links.edit');
        Route::put('/smart-link/{smartLink}', [\App\Http\Controllers\SmartLinkController::class, 'update'])->name('smart-links.update');
        Route::delete('/smart-link/{smartLink}', [\App\Http\Controllers\SmartLinkController::class, 'destroy'])->name('smart-links.destroy');

        Route::get('/ai-agent', [\App\Http\Controllers\AiAgentController::class, 'index'])->name('ai.index');
        Route::put('/ai-agent', [\App\Http\Controllers\AiAgentController::class, 'update'])->name('ai.update');
        Route::delete('/ai-agent/api-key', [\App\Http\Controllers\AiAgentController::class, 'forgetKey'])->name('ai.forget-key');
        Route::post('/ai-agent/uji', [\App\Http\Controllers\AiAgentController::class, 'test'])->name('ai.test');
        Route::post('/ai-agent/pengetahuan', [\App\Http\Controllers\AiAgentController::class, 'storeKnowledge'])->name('ai.knowledge.store');
        Route::put('/ai-agent/pengetahuan/{item}', [\App\Http\Controllers\AiAgentController::class, 'updateKnowledge'])->name('ai.knowledge.update');
        Route::delete('/ai-agent/pengetahuan/{item}', [\App\Http\Controllers\AiAgentController::class, 'destroyKnowledge'])->name('ai.knowledge.destroy');

        Route::get('/auto-reply', [\App\Http\Controllers\AutoReplyController::class, 'index'])->name('auto-replies.index');
        Route::get('/auto-reply/buat', [\App\Http\Controllers\AutoReplyController::class, 'create'])->name('auto-replies.create');
        Route::post('/auto-reply/uji', [\App\Http\Controllers\AutoReplyController::class, 'test'])->name('auto-replies.test');
        Route::post('/auto-reply', [\App\Http\Controllers\AutoReplyController::class, 'store'])->name('auto-replies.store');
        Route::get('/auto-reply/{autoReply}/ubah', [\App\Http\Controllers\AutoReplyController::class, 'edit'])->name('auto-replies.edit');
        Route::put('/auto-reply/{autoReply}', [\App\Http\Controllers\AutoReplyController::class, 'update'])->name('auto-replies.update');
        Route::post('/auto-reply/{autoReply}/toggle', [\App\Http\Controllers\AutoReplyController::class, 'toggle'])->name('auto-replies.toggle');
        Route::delete('/auto-reply/{autoReply}', [\App\Http\Controllers\AutoReplyController::class, 'destroy'])->name('auto-replies.destroy');

        Route::get('/perangkat', [DeviceController::class, 'index'])->name('devices.index');
        Route::get('/perangkat/tambah', [DeviceController::class, 'create'])->name('devices.create');
        Route::post('/perangkat', [DeviceController::class, 'store'])->name('devices.store');
        Route::post('/perangkat/scan', [DeviceController::class, 'scan'])->name('devices.scan');
        Route::get('/perangkat/{device}/ubah', [DeviceController::class, 'edit'])->name('devices.edit');
        Route::put('/perangkat/{device}', [DeviceController::class, 'update'])->name('devices.update');
        Route::delete('/perangkat/{device}', [DeviceController::class, 'destroy'])->name('devices.destroy');
        Route::post('/perangkat/{device}/utama', [DeviceController::class, 'makeDefault'])->name('devices.default');
        Route::post('/perangkat/{device}/cek', [DeviceController::class, 'check'])->name('devices.check');
        Route::get('/perangkat/{device}/pairing', [DeviceController::class, 'pair'])->name('devices.pair');
        Route::get('/perangkat/{device}/qr', [DeviceController::class, 'qr'])->name('devices.qr');
        Route::post('/perangkat/{device}/logout', [DeviceController::class, 'logout'])->name('devices.logout');

        Route::get('/api-key', [ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/api-key', [ApiKeyController::class, 'store'])->name('api-keys.store');
        Route::delete('/api-key/{apiKey}', [ApiKeyController::class, 'destroy'])->name('api-keys.destroy');

        Route::get('/webhook', [WebhookController::class, 'index'])->name('webhooks.index');
        Route::get('/webhook/tambah', [WebhookController::class, 'create'])->name('webhooks.create');
        Route::post('/webhook', [WebhookController::class, 'store'])->name('webhooks.store');
        Route::get('/webhook/{webhook}/ubah', [WebhookController::class, 'edit'])->name('webhooks.edit');
        Route::put('/webhook/{webhook}', [WebhookController::class, 'update'])->name('webhooks.update');
        Route::delete('/webhook/{webhook}', [WebhookController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('/webhook/{webhook}/tes', [WebhookController::class, 'test'])->name('webhooks.test');
        Route::post('/webhook/{webhook}/rotasi', [WebhookController::class, 'rotate'])->name('webhooks.rotate');
        Route::post('/webhook/kirim-ulang/{delivery}', [WebhookController::class, 'redeliver'])->name('webhooks.redeliver');

        Route::get('/pengguna', [UserController::class, 'index'])->name('users.index');
        Route::get('/pengguna/tambah', [UserController::class, 'create'])->name('users.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('users.store');
        Route::get('/pengguna/{user}/ubah', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('/log', [LogController::class, 'index'])->name('logs.index');
        Route::get('/pengaturan', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
    });
});
