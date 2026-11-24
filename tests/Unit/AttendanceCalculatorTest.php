<?php

use App\Models\Nexus\WorkSchedule;
use App\Services\AttendanceCalculator;

it('calculates delay and overtime for a late employee', function () {
    $schedule = new WorkSchedule([
        'check_in_time' => '09:00',
        'check_out_time' => '18:00',
        'is_rest_day' => false,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, '09:18', '18:40'))
        ->toMatchArray([
            'late_minutes' => 18,
            'overtime_hours' => 0.67,
            'status' => 'late',
        ]);
});

it('returns absent when there is no check in', function () {
    $schedule = new WorkSchedule([
        'check_in_time' => '09:00',
        'check_out_time' => '18:00',
        'is_rest_day' => false,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, null, null))
        ->toMatchArray([
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'status' => 'absent',
        ]);
});

it('returns day off without calculating delay or overtime', function () {
    $schedule = new WorkSchedule([
        'check_in_time' => null,
        'check_out_time' => null,
        'is_rest_day' => true,
    ]);

    expect(app(AttendanceCalculator::class)->calculate($schedule, '10:00', '15:00'))
        ->toMatchArray([
            'late_minutes' => 0,
            'overtime_hours' => 0,
            'status' => 'day_off',
        ]);
});
