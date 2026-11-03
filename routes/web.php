<?php

use App\Http\Controllers\CrmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreNominaController;
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

    Route::prefix('pre-nomina')->name('pre-nomina.')->group(function () {
        Route::get('/', [PreNominaController::class, 'index'])->name('index');
        Route::get('empleados', [PreNominaController::class, 'empleados'])->name('empleados');
        Route::get('asistencias', [PreNominaController::class, 'asistencias'])->name('asistencias');
    });
});

require __DIR__.'/settings.php';
