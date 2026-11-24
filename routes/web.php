<?php

use App\Http\Controllers\admin\CompanyController;
use App\Http\Controllers\admin\CompanyUserController;
use App\Http\Controllers\admin\PlatformAdminController;
use App\Http\Controllers\crm\CrmController;
use App\Http\Controllers\crm\MetaAssetController;
use App\Http\Controllers\crm\MetaCampaignController;
use App\Http\Controllers\crm\MetaConnectionController;
use App\Http\Controllers\crm\MetaLeadImportController;
use App\Http\Controllers\crm\MetaReadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\preNomina\PreNominaController;
use App\Http\Middleware\EnsureCompanyRole;
use App\Http\Middleware\EnsureCompanyUser;
use App\Http\Middleware\EnsurePlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('home', fn () => redirect()->route(auth()->user()->is_platform_admin ? 'admin.index' : 'dashboard'))->name('app.home');

    Route::prefix('admin')->name('admin.')->middleware(EnsurePlatformAdmin::class)->group(function () {
        Route::get('/', [PlatformAdminController::class, 'index'])->name('index');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('users', [CompanyUserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [CompanyUserController::class, 'update'])->name('users.update');
        Route::post('users/{user}/resend-invitation', [CompanyUserController::class, 'resend'])->name('users.resend');
    });

    Route::middleware(EnsureCompanyUser::class)->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('crm')->name('crm.')->middleware(EnsureCompanyRole::class.':Administrador,Marketing')->group(function () {
            Route::get('meta/ad-accounts', [MetaReadController::class, 'adAccounts'])->name('meta.ad-accounts.index');
            Route::get('meta/oauth/redirect', [MetaConnectionController::class, 'redirect'])->name('meta.oauth.redirect');
            Route::get('meta/oauth/callback', [MetaConnectionController::class, 'callback'])->name('meta.oauth.callback');
            Route::get('meta/businesses', [MetaConnectionController::class, 'businesses'])->name('meta.businesses.index');
            Route::post('meta/business', [MetaConnectionController::class, 'selectBusiness'])->name('meta.business.select');
            Route::get('meta/pages', [MetaAssetController::class, 'pages'])->name('meta.pages.index');
            Route::post('meta/ad-accounts', [MetaAssetController::class, 'linkAccount'])->name('meta.ad-accounts.link');
            Route::post('meta/pages', [MetaAssetController::class, 'linkPage'])->name('meta.pages.link');
            Route::get('meta/campaigns', [MetaCampaignController::class, 'list'])->name('meta.campaigns.index');
            Route::post('meta/campaigns/save', [MetaCampaignController::class, 'save'])->name('meta.campaigns.save');
            Route::post('meta/leads/import', [MetaLeadImportController::class, 'store'])->name('meta.leads.import');
            Route::get('campanas', [MetaCampaignController::class, 'index'])->name('campanas');
            Route::get('leads', [CrmController::class, 'leads'])->name('leads');
            Route::get('clientes', [CrmController::class, 'clientes'])->name('clientes');
            Route::get('clientes/{cliente}', [CrmController::class, 'cliente'])->name('clientes.show');
            Route::get('asistente', [CrmController::class, 'asistente'])->name('asistente');
            Route::get('configuracion', [CrmController::class, 'configuracion'])->middleware(EnsureCompanyRole::class.':Administrador')->name('configuracion');
        });

        Route::prefix('pre-nomina')->name('pre-nomina.')->middleware(EnsureCompanyRole::class.':Administrador,Recursos Humanos')->group(function () {
            Route::get('/', [PreNominaController::class, 'index'])->name('index');
            Route::get('empleados', [PreNominaController::class, 'empleados'])->name('empleados');
            Route::get('asistencias', [PreNominaController::class, 'asistencias'])->name('asistencias');
        });
    });
});

require __DIR__.'/settings.php';
