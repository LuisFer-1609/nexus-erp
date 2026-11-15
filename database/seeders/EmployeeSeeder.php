<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            [
                'employee_number' => 'EMP-001',
                'first_name' => 'Ana Sofía',
                'last_name' => 'Martínez',
                'email' => 'ana.martinez@nexuserp.test',
                'department' => 'Administración',
                'position' => 'Coordinadora administrativa',
                'hired_at' => '2024-02-12',
            ],
            [
                'employee_number' => 'EMP-002',
                'first_name' => 'Carlos',
                'last_name' => 'Ramírez López',
                'email' => 'carlos.ramirez@nexuserp.test',
                'department' => 'Ventas',
                'position' => 'Ejecutivo de ventas',
                'hired_at' => '2023-09-04',
            ],
            [
                'employee_number' => 'EMP-003',
                'first_name' => 'Mariana',
                'last_name' => 'Torres García',
                'email' => 'mariana.torres@nexuserp.test',
                'department' => 'Recursos humanos',
                'position' => 'Analista de personal',
                'hired_at' => '2025-01-20',
            ],
            [
                'employee_number' => 'EMP-004',
                'first_name' => 'Diego',
                'last_name' => 'Hernández Cruz',
                'email' => 'diego.hernandez@nexuserp.test',
                'department' => 'Operaciones',
                'position' => 'Supervisor de operaciones',
                'hired_at' => '2022-06-13',
            ],
        ];

        foreach ($employees as $employeeData) {
            $employee = Employee::updateOrCreate(
                ['employee_number' => $employeeData['employee_number']],
                $employeeData,
            );

            foreach (range(1, 7) as $dayOfWeek) {
                $isDayOff = $dayOfWeek >= 6;

                $employee->workSchedules()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    [
                        'start_time' => $isDayOff ? null : '09:00',
                        'end_time' => $isDayOff ? null : ($dayOfWeek === 5 ? '16:00' : '18:00'),
                        'is_day_off' => $isDayOff,
                    ],
                );
            }
        }
    }
}
