<?php

use App\Http\Controllers\crm\CrmController;
use App\Http\Controllers\DashboardController;
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

require __DIR__.'/settings.php';
