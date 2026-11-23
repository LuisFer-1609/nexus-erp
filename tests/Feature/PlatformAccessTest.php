<?php

use App\Models\User;
use App\Notifications\CompanyInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

test('platform administrator can invite company users but cannot read company data', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_platform_admin' => true]);
    $companyId = DB::table('companies')->insertGetId(['name' => 'Acme']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);

    $this->actingAs($admin)->get('/admin')->assertOk();

    $this->actingAs($admin)->post('/admin/users', [
        'name' => 'Ana',
        'email' => 'ana@example.test',
        'company_id' => $companyId,
        'role_id' => $roleId,
    ])->assertRedirect();

    $user = User::query()->where('email', 'ana@example.test')->firstOrFail();
    expect($user->company_id)->toBe($companyId)
        ->and($user->password_set_at)->toBeNull()
        ->and(DB::table('users')->where('id', $user->erp_user_id)->value('company_id'))->toBe($companyId);
    Notification::assertSentTo($user, CompanyInvitation::class);
    $this->actingAs($admin)->get('/crm/leads')->assertForbidden();
    $user->forceFill(['email_verified_at' => now(), 'password_set_at' => now()])->save();
    $this->actingAs($user)->get('/admin')->assertForbidden();

    $otherCompanyId = DB::table('companies')->insertGetId(['name' => 'Otra empresa']);
    $this->actingAs($admin)->patch("/admin/users/{$user->id}", [
        'company_id' => $otherCompanyId,
        'role_id' => $roleId,
    ])->assertSessionHasErrors('company_id');
    expect($user->fresh()->company_id)->toBe($companyId);
});

test('invited user cannot sign in until setting a password', function () {
    $companyId = DB::table('companies')->insertGetId(['name' => 'Acme']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Marketing', 'created_at' => now()]);
    $user = User::factory()->create([
        'company_id' => $companyId,
        'role_id' => $roleId,
        'password' => Hash::make('Secure-Password-123!'),
        'password_set_at' => null,
        'email_verified_at' => null,
    ]);

    $this->post('/login', ['email' => $user->email, 'password' => 'Secure-Password-123!'])
        ->assertSessionHasErrors();

    $token = Password::broker()->createToken($user);
    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'New-Secure-Password-123!',
        'password_confirmation' => 'New-Secure-Password-123!',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->password_set_at)->not->toBeNull()
        ->and($user->fresh()->email_verified_at)->not->toBeNull();
});
