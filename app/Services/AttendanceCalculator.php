<?php

namespace App\Services;

use App\Models\Nexus\WorkSchedule;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * @return array{late_minutes: int, overtime_hours: float, status: string}
     */
    public function calculate(WorkSchedule $schedule, ?string $checkIn, ?string $checkOut): array
    {
        if ($schedule->is_rest_day) {
            return [
                'late_minutes' => 0,
                'overtime_hours' => 0,
                'status' => 'day_off',
            ];
        }

        if ($checkIn === null) {
            return [
                'late_minutes' => 0,
                'overtime_hours' => 0,
                'status' => 'absent',
            ];
        }

        $expectedStart = Carbon::createFromFormat('H:i:s', $this->normalizeTime($schedule->check_in_time));
        $expectedEnd = Carbon::createFromFormat('H:i:s', $this->normalizeTime($schedule->check_out_time));
        $actualCheckIn = Carbon::createFromFormat('H:i:s', $this->normalizeTime($checkIn));
        $actualCheckOut = $checkOut === null
            ? null
            : Carbon::createFromFormat('H:i:s', $this->normalizeTime($checkOut));

        return [
            'late_minutes' => max(0, $expectedStart->diffInMinutes($actualCheckIn, false)),
            'overtime_hours' => $actualCheckOut === null
                ? 0
                : round(max(0, $expectedEnd->diffInMinutes($actualCheckOut, false)) / 60, 2),
            'status' => $expectedStart->diffInMinutes($actualCheckIn, false) > 0
                ? 'late'
                : 'on_time',
        ];
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
