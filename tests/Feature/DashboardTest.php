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

test('dashboard counts only recent leads from the user company', function () {
    $companyId = DB::table('companies')->insertGetId(['name' => 'Empresa']);
    $otherCompanyId = DB::table('companies')->insertGetId(['name' => 'Otra empresa']);
    $roleId = DB::table('roles')->insertGetId(['name' => 'Administrador', 'created_at' => now()]);
    $user = User::factory()->create(['company_id' => $companyId, 'role_id' => $roleId]);
    $stageId = DB::table('lead_stages')->insertGetId(['code' => 'nuevo', 'name' => 'Nuevo', 'position' => 1]);
    $sourceId = DB::table('lead_sources')->insertGetId(['code' => 'meta', 'name' => 'Meta']);

    $lead = fn (int $company, string $createdAt) => [
        'company_id' => $company,
        'lead_source_id' => $sourceId,
        'lead_stage_id' => $stageId,
        'full_name' => 'Prospecto',
        'created_at' => $createdAt,
    ];
    DB::table('leads')->insert([
        $lead($companyId, now()->subDays(2)->toDateTimeString()),
        $lead($companyId, now()->subDays(60)->toDateTimeString()),
        $lead($otherCompanyId, now()->subDays(2)->toDateTimeString()),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('leadStats.total', 1)
            ->where('leadStats.byStage.0.name', 'Nuevo')
            ->where('leadStats.byStage.0.count', 1)
            ->where('leadStats.bySource.0.name', 'Meta')
            ->where('leadStats.bySource.0.count', 1));
});
