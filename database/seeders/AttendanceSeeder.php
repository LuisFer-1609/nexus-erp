<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::query()->pluck('id', 'employee_number');

        $records = [
            [
                'employee_number' => 'EMP-001',
                'attendance_date' => '2026-11-17',
                'check_in' => '08:55',
                'check_out' => '18:05',
                'delay_minutes' => 0,
                'overtime_minutes' => 5,
                'status' => 'on_time',
            ],
            [
                'employee_number' => 'EMP-002',
                'attendance_date' => '2026-11-17',
                'check_in' => '09:18',
                'check_out' => '18:00',
                'delay_minutes' => 18,
                'overtime_minutes' => 0,
                'status' => 'late',
            ],
            [
                'employee_number' => 'EMP-003',
                'attendance_date' => '2026-11-17',
                'check_in' => null,
                'check_out' => null,
                'delay_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => 'absent',
            ],
            [
                'employee_number' => 'EMP-004',
                'attendance_date' => '2026-11-17',
                'check_in' => '08:58',
                'check_out' => '18:40',
                'delay_minutes' => 0,
                'overtime_minutes' => 40,
                'status' => 'on_time',
            ],
        ];

        foreach (['EMP-001', 'EMP-002', 'EMP-003', 'EMP-004'] as $employeeNumber) {
            $records[] = [
                'employee_number' => $employeeNumber,
                'attendance_date' => '2026-11-21',
                'check_in' => null,
                'check_out' => null,
                'delay_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => 'day_off',
            ];
        }

        foreach ($records as $record) {
            Attendance::updateOrCreate(
                [
                    'employee_id' => $employees[$record['employee_number']],
                    'attendance_date' => $record['attendance_date'],
                ],
                [
                    'check_in' => $record['check_in'],
                    'check_out' => $record['check_out'],
                    'delay_minutes' => $record['delay_minutes'],
                    'overtime_minutes' => $record['overtime_minutes'],
                    'status' => $record['status'],
                    'is_manual' => false,
                ],
            );
        }
    }
}
