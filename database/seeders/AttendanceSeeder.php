<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $records = [
            ['code' => 'EMP-001', 'date' => '2026-11-17', 'in' => '08:55', 'out' => '18:05', 'late' => 0, 'extra' => 5 / 60, 'status' => 'on_time'],
            ['code' => 'EMP-002', 'date' => '2026-11-17', 'in' => '09:18', 'out' => '18:00', 'late' => 18, 'extra' => 0, 'status' => 'late'],
            ['code' => 'EMP-003', 'date' => '2026-11-17', 'in' => null, 'out' => null, 'late' => 0, 'extra' => 0, 'status' => 'absent'],
            ['code' => 'EMP-004', 'date' => '2026-11-17', 'in' => '08:58', 'out' => '18:40', 'late' => 0, 'extra' => 40 / 60, 'status' => 'on_time'],
        ];
        foreach (['EMP-001', 'EMP-002', 'EMP-003', 'EMP-004'] as $code) {
            $records[] = ['code' => $code, 'date' => '2026-11-21', 'in' => null, 'out' => null, 'late' => 0, 'extra' => 0, 'status' => 'day_off'];
        }
        foreach ($records as $record) {
            $employeeId = DB::table('employees')->where('code', $record['code'])->value('id');
            DB::table('attendances')->updateOrInsert(['employee_id' => $employeeId, 'attendance_date' => $record['date']], ['check_in' => $record['in'], 'check_out' => $record['out'], 'expected_check_in' => '09:00', 'expected_check_out' => '18:00', 'late_minutes' => $record['late'], 'overtime_hours' => $record['extra'], 'status' => $record['status']]);
        }
    }
}
