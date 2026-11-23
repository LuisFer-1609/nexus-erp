<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $companyId = DB::table('companies')->insertGetId(['name' => 'Empresa']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);
    $user = User::factory()->create(['company_id' => $companyId, 'role_id' => $roleId]);
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
