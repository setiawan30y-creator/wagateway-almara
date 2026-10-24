<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data awal: akun admin (dari .env) + perangkat simulasi.
 * @2026-Alifiandra
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => env('ADMIN_PASSWORD', 'ubah-password-ini'),
                'role' => 'owner',
            ],
        );

        if (Device::count() === 0) {
            Device::create([
                'name' => 'Simulasi',
                'provider' => 'log',
                'credentials' => [],
                'status' => 'connected',
                'is_default' => true,
                'is_active' => true,
            ]);
        }
    }
}
