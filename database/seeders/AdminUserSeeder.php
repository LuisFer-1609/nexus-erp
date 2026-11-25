<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@utt.com'],
            [
                'name' => 'Administrador NexusERP',
                'email_verified_at' => now(),
                'password' => Hash::make($password),
                'is_platform_admin' => true,
                'company_id' => null,
                'role_id' => null,
            ],
        );

        // Nexus IA responde con los datos de la empresa del usuario: si el
        // administrador aún no tiene una, se le asigna la primera empresa.
        if ($admin->company_id === null) {
            $companyId = DB::table('companies')->orderBy('id')->value('id');
            if ($companyId !== null) {
                $admin->forceFill([User::COMPANY_ID => (int) $companyId])->save();
            }
        }
    }
}
