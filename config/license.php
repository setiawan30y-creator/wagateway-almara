<?php

/*
|--------------------------------------------------------------------------
| Paket & lisensi (untuk aplikasi yang disewakan)
|--------------------------------------------------------------------------
| Tanpa kode lisensi yang sah, aplikasi berjalan sebagai paket "free".
| Kode lisensi ditandatangani (ECDSA P-256) dengan kunci privat milik pemilik aplikasi;
| aplikasi hanya menyimpan kunci publik (config/license-public.pem) untuk memeriksa.
| Buat kunci:      php artisan license:keygen
| Terbitkan kode:  php artisan license:issue "Nama Penyewa" --plan=pro --days=30
| @2026-Alifiandra
*/

return [

    // Lokasi kunci privat (JANGAN ikut dibagikan ke penyewa). Default: folder home pengguna.
    'private_key_path' => env('LICENSE_PRIVATE_KEY_PATH'),

    // Kunci publik untuk verifikasi (file ini ikut dibagikan).
    'public_key_path' => base_path('config/license-public.pem'),

    // Hanya berlaku di lingkungan "testing" (agar tes tidak terkena batas paket).
    'force_plan' => env('LICENSE_FORCE_PLAN'),

    // Masa tenggang setelah lisensi kedaluwarsa sebelum turun ke paket Gratis.
    'grace_days' => 3,

    'plans' => [
        'free' => [
            'label' => 'Gratis',
            'devices' => 1,            // nomor WhatsApp aktif
            'daily_messages' => 100,   // pesan keluar per hari (semua nomor)
            'broadcast' => false,
            'ai' => false,
            'watermark' => true,
        ],
        'pro' => [
            'label' => 'Pro',
            'devices' => 5,            // bisa diganti per lisensi (--devices)
            'daily_messages' => null,  // tanpa batas
            'broadcast' => true,
            'ai' => true,
            'watermark' => false,
        ],
    ],

    // Teks watermark di akhir setiap pesan keluar paket Gratis
    'watermark_text' => "\n\n_Dikirim via ALMARA Gateway WA_",

    // Ditampilkan di halaman Lisensi untuk calon penyewa
    'contact' => env('LICENSE_CONTACT', 'Hubungi penyedia aplikasi untuk upgrade ke Pro.'),
];
