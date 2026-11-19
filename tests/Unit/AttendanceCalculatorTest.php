<?php

use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;

it('calculates delay and overtime for a late employee', function () {
    $schedule = new WorkSchedule([
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_day_off' => false,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, '09:18', '18:40'))
        ->toMatchArray([
            'delay_minutes' => 18,
            'overtime_minutes' => 40,
            'status' => 'late',
        ]);
});

it('returns absent when there is no check in', function () {
    $schedule = new WorkSchedule([
        'start_time' => '09:00',
        'end_time' => '18:00',
        'is_day_off' => false,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, null, null))
        ->toMatchArray([
            'delay_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'absent',
        ]);
});

it('returns day off without calculating delay or overtime', function () {
    $schedule = new WorkSchedule([
        'start_time' => null,
        'end_time' => null,
        'is_day_off' => true,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, '10:00', '15:00'))
        ->toMatchArray([
            'delay_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'day_off',
        ]);
});
