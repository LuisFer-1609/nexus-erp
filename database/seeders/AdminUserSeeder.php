<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account.
     */
    public function run(): void
    {
        $password = config('nexus.admin_password');

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('NEXUS_ADMIN_PASSWORD must be configured before running AdminUserSeeder.');
        }

        User::query()->firstOrCreate(
            ['email' => 'admin@utt.com'],
            [
                'name' => 'Administrador NexusERP',
                'email_verified_at' => now(),
                'password' => Hash::make($password),
            ],
        );
    }
}
