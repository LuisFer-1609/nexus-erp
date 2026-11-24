<?php

use App\Http\Controllers\crm\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ia\IaActionController;
use App\Http\Controllers\ia\IaAttachmentController;
use App\Http\Controllers\ia\IaChatController;
use App\Http\Controllers\ia\IaConversationController;
use App\Http\Controllers\ia\IaReportController;
use App\Http\Controllers\ia\WhatsappController;
use App\Http\Controllers\preNomina\PreNominaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('crm')->name('crm.')->group(function () {
        Route::get('leads', [CrmController::class, 'leads'])->name('leads');
        Route::get('clientes', [CrmController::class, 'clientes'])->name('clientes');
        Route::get('clientes/{cliente}', [CrmController::class, 'cliente'])->name('clientes.show');
        Route::get('asistente', [CrmController::class, 'asistente'])->name('asistente');
        Route::get('configuracion', [CrmController::class, 'configuracion'])->name('configuracion');
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

        // Endpoints Funcionales que persisten en la BD
        Route::post('ajustar', [PreNominaController::class, 'guardarAjuste'])->name('ajustar');
        Route::post('recalcular', [PreNominaController::class, 'recalcular'])->name('recalcular');
        Route::post('crear-periodo', [PreNominaController::class, 'crearPeriodo'])->name('crear-periodo');
        Route::post('cambiar-estado', [PreNominaController::class, 'cambiarEstado'])->name('cambiar-estado');
        Route::post('aprobar-masivo', [PreNominaController::class, 'aprobarMasivo'])->name('aprobar-masivo');
    });
});

require __DIR__.'/settings.php';
