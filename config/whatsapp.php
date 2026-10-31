<?php

/*
|--------------------------------------------------------------------------
| ALMARA Gateway WA — konfigurasi
|--------------------------------------------------------------------------
| @2026-Alifiandra
*/

return [

    'version' => '0.8.0',

    // Kode negara default untuk normalisasi nomor lokal (08xx -> 628xx)
    'default_country_code' => env('WA_DEFAULT_COUNTRY_CODE', '62'),

    // Batas request API per API key per menit
    'api_rate_limit' => (int) env('WA_API_RATE_LIMIT', 60),

    // Panjang maksimum isi pesan teks
    'max_text_length' => 4096,

    'providers' => [
        'log' => [
            'label' => 'Simulasi (Log)',
            'class' => App\WhatsApp\Providers\LogProvider::class,
        ],
        'meta' => [
            'label' => 'Meta Cloud API (resmi)',
            'class' => App\WhatsApp\Providers\MetaCloudProvider::class,
        ],
        'qr' => [
            'label' => 'WhatsApp (Scan QR)',
            'class' => App\WhatsApp\Providers\QrEngineProvider::class,
        ],
    ],

    'meta' => [
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'app_secret' => env('META_APP_SECRET'),
    ],

    'http_timeout' => (int) env('WA_HTTP_TIMEOUT', 20),

    // Engine QR bawaan (folder engine/, Node.js) untuk tambah perangkat via scan barcode
    'engine' => [
        'url' => env('WA_ENGINE_URL', 'http://127.0.0.1:3000'),
        'token' => env('WA_ENGINE_TOKEN'),
    ],
];
