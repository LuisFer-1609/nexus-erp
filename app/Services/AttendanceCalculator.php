<?php

namespace App\Services;

use App\Models\WorkSchedule;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * @return array{delay_minutes: int, overtime_minutes: int, status: string}
     */
    public function calculate(WorkSchedule $schedule, ?string $checkIn, ?string $checkOut): array
    {
        if ($schedule->is_day_off) {
            return [
                'delay_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => 'day_off',
            ];
        }

        if ($checkIn === null) {
            return [
                'delay_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => 'absent',
            ];
        }

        $expectedStart = Carbon::createFromFormat('H:i:s', $this->normalizeTime($schedule->start_time));
        $expectedEnd = Carbon::createFromFormat('H:i:s', $this->normalizeTime($schedule->end_time));
        $actualCheckIn = Carbon::createFromFormat('H:i:s', $this->normalizeTime($checkIn));
        $actualCheckOut = $checkOut === null
            ? null
            : Carbon::createFromFormat('H:i:s', $this->normalizeTime($checkOut));

        return [
            'delay_minutes' => max(0, $expectedStart->diffInMinutes($actualCheckIn, false)),
            'overtime_minutes' => $actualCheckOut === null
                ? 0
                : max(0, $expectedEnd->diffInMinutes($actualCheckOut, false)),
            'status' => $expectedStart->diffInMinutes($actualCheckIn, false) > 0
                ? 'late'
                : 'on_time',
        ];
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }
}
