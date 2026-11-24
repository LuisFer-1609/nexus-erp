<?php

use App\Services\Ai\Reports\ReportService;

test('las fechas AAAA-MM-DD de una gráfica se muestran como día de la semana en español', function () {
    $labels = app(ReportService::class)->chartLabels(['2026-11-23', '2026-11-24']);

    expect($labels)->toBe(['lunes 23 nov.', 'martes 24 nov.']);
});

test('las etiquetas que no son fechas válidas no cambian', function () {
    $labels = app(ReportService::class)->chartLabels(['Ventas', '2026-02-30', 'Etapa 1']);

    expect($labels)->toBe(['Ventas', '2026-02-30', 'Etapa 1']);
});

test('las fechas de las celdas del reporte llevan el nombre del día, con la hora si la traen', function () {
    $service = app(ReportService::class);

    expect($service->cellValue('2026-11-13'))->toBe('viernes 13 nov. 2026')
        ->and($service->cellValue('2026-11-13 09:17:00'))->toBe('viernes 13 nov. 2026 09:17')
        ->and($service->cellValue('09:17:00'))->toBe('09:17:00')
        ->and($service->cellValue(17))->toBe(17)
        ->and($service->cellValue('Ana Torres'))->toBe('Ana Torres')
        ->and($service->cellValue('2026-02-30'))->toBe('2026-02-30')
        ->and($service->cellValue(null))->toBeNull();
});
