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
use App\Http\Controllers\ia\IaActionController;
use App\Http\Controllers\ia\IaAttachmentController;
use App\Http\Controllers\ia\IaChatController;
use App\Http\Controllers\ia\IaConversationController;
use App\Http\Controllers\ia\IaReportController;
use App\Http\Controllers\ia\WhatsappController;
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
            Route::delete('meta/ad-accounts/{accountId}', [MetaAssetController::class, 'unlinkAccount'])->name('meta.ad-accounts.unlink');
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

        Route::prefix('ia')->name('ia.')->group(function () {
            Route::post('chat', IaChatController::class)->middleware('throttle:30,1')->name('chat');
            Route::get('whatsapp', [WhatsappController::class, 'index'])->name('whatsapp');
            Route::post('whatsapp/iniciar', [WhatsappController::class, 'start'])->middleware('throttle:10,1')->name('whatsapp.iniciar');
            Route::get('whatsapp/estado', [WhatsappController::class, 'status'])->middleware('throttle:90,1')->name('whatsapp.estado');
            Route::delete('whatsapp', [WhatsappController::class, 'destroy'])->middleware('throttle:10,1')->name('whatsapp.desvincular');
            Route::get('conversaciones', [IaConversationController::class, 'index'])->middleware('throttle:60,1')->name('conversaciones.index');
            Route::delete('conversaciones', [IaConversationController::class, 'destroyAll'])->middleware('throttle:10,1')->name('conversaciones.destroyAll');
            Route::get('conversaciones/{conversation}', [IaConversationController::class, 'show'])->middleware('throttle:60,1')->name('conversaciones.show');
            Route::delete('conversaciones/{conversation}', [IaConversationController::class, 'destroy'])->middleware('throttle:30,1')->name('conversaciones.destroy');
            Route::delete('conversaciones/{conversation}/mensajes/{message}', [IaConversationController::class, 'destroyMessage'])->middleware('throttle:60,1')->name('conversaciones.mensajes.destroy');
            Route::get('adjuntos/{attachment}', [IaAttachmentController::class, 'show'])->name('adjuntos.ver');
            Route::get('adjuntos/{attachment}/miniatura', [IaAttachmentController::class, 'thumbnail'])->middleware('throttle:120,1')->name('adjuntos.miniatura');
            Route::get('reportes/{report}', [IaReportController::class, 'download'])->name('reportes.descargar');
            Route::get('reportes/{report}/ver', [IaReportController::class, 'preview'])->name('reportes.ver');
            Route::get('reportes/{report}/miniatura', [IaReportController::class, 'thumbnail'])->middleware('throttle:120,1')->name('reportes.miniatura');
            Route::post('acciones/{action}/confirmar', [IaActionController::class, 'confirm'])->middleware('throttle:20,1')->name('acciones.confirmar');
            Route::post('acciones/{action}/cancelar', [IaActionController::class, 'cancel'])->middleware('throttle:20,1')->name('acciones.cancelar');
        });

        Route::prefix('pre-nomina')->name('pre-nomina.')->group(function () {
            Route::get('/', [PreNominaController::class, 'index'])->name('index');
            Route::get('empleados', [PreNominaController::class, 'empleados'])->name('empleados');
            Route::get('asistencias', [PreNominaController::class, 'asistencias'])->name('asistencias');
            Route::get('asistencias/{attendance}', [PreNominaController::class, 'attendanceDetail'])->name('asistencias.show');
            Route::put('asistencias/{attendance}', [PreNominaController::class, 'updateAttendance'])->name('asistencias.update');
            Route::put('empleados/{employee}/horarios/{workSchedule}', [PreNominaController::class, 'updateSchedule'])->name('horarios.update');

            // Endpoints Funcionales que persisten en la BD
            Route::post('ajustar', [PreNominaController::class, 'guardarAjuste'])->name('ajustar');
            Route::post('recalcular', [PreNominaController::class, 'recalcular'])->name('recalcular');
            Route::post('crear-periodo', [PreNominaController::class, 'crearPeriodo'])->name('crear-periodo');
            Route::post('cambiar-estado', [PreNominaController::class, 'cambiarEstado'])->name('cambiar-estado');
            Route::post('aprobar-masivo', [PreNominaController::class, 'aprobarMasivo'])->name('aprobar-masivo');
        });
    });
});

require __DIR__.'/settings.php';