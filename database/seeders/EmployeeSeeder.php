<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            ['code' => 'EMP-001', 'name' => 'Ana Sofía', 'last_name' => 'Martínez'],
            ['code' => 'EMP-002', 'name' => 'Carlos', 'last_name' => 'Ramírez López'],
            ['code' => 'EMP-003', 'name' => 'Mariana', 'last_name' => 'Torres García'],
            ['code' => 'EMP-004', 'name' => 'Diego', 'last_name' => 'Hernández Cruz'],
        ];
        foreach ($employees as $data) {
            DB::table('users')->updateOrInsert(['nickname' => strtolower($data['code'])], ['company_id' => 1, 'role_id' => 1, 'nickname' => strtolower($data['code']), 'name' => $data['name'], 'last_name' => $data['last_name'], 'passwd' => password_hash('password', PASSWORD_BCRYPT), 'is_active' => 1]);
            $userId = DB::table('users')->where('nickname', strtolower($data['code']))->value('id');
            DB::table('employees')->updateOrInsert(['code' => $data['code']], ['id_user' => $userId, 'nationality_id' => 72, 'payroll_frequency_id' => 1, 'area_id' => 6, 'position_id' => 1, 'code' => $data['code'], 'hire_date' => '2024-01-01', 'status' => 'activo']);
            $employeeId = DB::table('employees')->where('code', $data['code'])->value('id');
            foreach (range(1, 7) as $weekday) {
                $rest = $weekday >= 6;
                DB::table('work_schedules')->updateOrInsert(['employee_id' => $employeeId, 'weekday' => $weekday, 'valid_from' => '2026-01-01'], ['check_in_time' => $rest ? null : '09:00', 'check_out_time' => $rest ? null : ($weekday === 5 ? '16:00' : '18:00'), 'is_rest_day' => $rest]);
            }
        }
    }
}
