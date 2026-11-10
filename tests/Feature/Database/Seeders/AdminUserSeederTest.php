<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('admin user seeder creates the initial administrator with a hashed password', function () {
    config()->set('nexus.admin_password', 'Temporary-Test-Password_123!');

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'admin@utt.com')->firstOrFail();

    expect($admin->name)->toBe('Administrador NexusERP')
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and($admin->password)->not->toBe('Temporary-Test-Password_123!')
        ->and(Hash::check('Temporary-Test-Password_123!', $admin->password))->toBeTrue();
});

test('admin user seeder is idempotent', function () {
    config()->set('nexus.admin_password', 'Temporary-Test-Password_123!');

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::query()->where('email', 'admin@utt.com')->count())->toBe(1);
});
