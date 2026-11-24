<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Datos de prueba para Nexus IA: dos empresas con leads, empleados y
 * asistencias. Sirven para probar la carga y comprobar que la búsqueda solo
 * devuelve datos de la empresa consultada.
 *
 * Uso: php artisan db:seed --class=NexusAiDemoSeeder
 * Es idempotente: se puede ejecutar varias veces sin duplicar datos.
 */
class NexusAiDemoSeeder extends Seeder
{
    /** Rol "General" del catálogo por defecto. */
    private const ROLE_GENERAL = 4;

    public function run(): void
    {
        $nexusId = (int) (DB::table('companies')->where('name', 'Nexus Interno (Pruebas)')->value('id') ?? 0);
        if ($nexusId === 0) {
            $nexusId = $this->company('Nexus Interno (Pruebas)', 'Nexus Pruebas Internas S.A. de C.V.', 'Software');
        }

        $auroraId = $this->company('Distribuidora Aurora (Demo)', 'Distribuidora Aurora S.A. de C.V.', 'Comercio');

        $this->seedCompany($nexusId, [
            'areas' => ['RH' => 'Recursos Humanos', 'VTA' => 'Ventas'],
            'positions' => [
                'GER-RH' => ['Gerente de Recursos Humanos', 'RH'],
                'EJE-VTA' => ['Ejecutivo de ventas', 'VTA'],
            ],
            'employees' => [
                ['EMP-N01', 'ana.torres', 'Ana', 'Torres', 'F', 'RH', 'GER-RH', '2024-02-05'],
                ['EMP-N02', 'luis.mendoza', 'Luis', 'Mendoza', 'M', 'VTA', 'EJE-VTA', '2024-06-17'],
                ['EMP-N03', 'sofia.ramirez', 'Sofía', 'Ramírez', 'F', 'VTA', 'EJE-VTA', '2025-01-13'],
            ],
            'leads' => [
                ['LD-N01', 'Carlos Beltrán', 'carlos.beltran@correo.test', '8711000001', 'alta', 'nuevo', 'facebook', 'luis.mendoza'],
                ['LD-N02', 'Distribuidora Norte SA', 'compras@norte.test', '8711000002', 'media', 'contactado', 'instagram', 'luis.mendoza'],
                ['LD-N03', 'Mariana Ortiz', 'mariana.ortiz@correo.test', '8711000003', 'alta', 'negociacion', 'facebook', 'sofia.ramirez'],
                ['LD-N04', 'Grupo Tecnor', 'contacto@tecnor.test', '8711000004', 'baja', 'nuevo', 'instagram', 'sofia.ramirez'],
                ['LD-N05', 'Pedro Salinas', 'pedro.salinas@correo.test', '8711000005', 'media', 'cerrado', 'facebook', 'luis.mendoza'],
                ['LD-N06', 'Lucía Fernández', 'lucia.fernandez@correo.test', '8711000006', 'alta', 'contactado', 'instagram', 'sofia.ramirez'],
            ],
        ]);

        $this->seedCompany($auroraId, [
            'areas' => ['RH' => 'Recursos Humanos', 'VTA' => 'Ventas'],
            'positions' => [
                'AUX-RH' => ['Auxiliar de Recursos Humanos', 'RH'],
                'EJE-VTA' => ['Ejecutivo de ventas', 'VTA'],
            ],
            'employees' => [
                ['EMP-A01', 'jorge.valdes', 'Jorge', 'Valdés', 'M', 'RH', 'AUX-RH', '2023-09-11'],
                ['EMP-A02', 'camila.nunez', 'Camila', 'Núñez', 'F', 'VTA', 'EJE-VTA', '2024-03-04'],
                ['EMP-A03', 'raul.esquivel', 'Raúl', 'Esquivel', 'M', 'VTA', 'EJE-VTA', '2025-05-26'],
            ],
            'leads' => [
                ['LD-A01', 'Panadería La Espiga', 'pedidos@laespiga.test', '8712000001', 'alta', 'nuevo', 'instagram', 'camila.nunez'],
                ['LD-A02', 'Roberto Quiroz', 'roberto.quiroz@correo.test', '8712000002', 'media', 'contactado', 'facebook', 'raul.esquivel'],
                ['LD-A03', 'Hotel Mirador', 'reservas@mirador.test', '8712000003', 'alta', 'negociacion', 'facebook', 'camila.nunez'],
                ['LD-A04', 'Elena Cárdenas', 'elena.cardenas@correo.test', '8712000004', 'baja', 'cerrado', 'instagram', 'raul.esquivel'],
                ['LD-A05', 'Constructora Bravo', 'obras@bravo.test', '8712000005', 'media', 'nuevo', 'facebook', 'camila.nunez'],
            ],
        ]);

        // @phpstan-ignore nullsafe.neverNull
        $this->command?->info('Datos de prueba de Nexus IA listos (2 empresas). Ejecuta: php artisan nexus:ai-sync');
    }

    private function company(string $name, string $businessName, string $business): int
    {
        $existing = DB::table('companies')->where('name', $name)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('companies')->insertGetId([
            'name' => $name,
            'business_name' => $businessName,
            'rfc' => 'XAXX010101000',
            'type_of_business' => $business,
            'mail' => Str::slug($name).'@demo.test',
            'phone' => '8710000000',
            'address' => 'Dirección de pruebas',
            'is_active' => 1,
        ]);
    }

    /**
     * @param  array{areas: array<string, string>, positions: array<string, array{0: string, 1: string}>, employees: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}>, leads: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: string}>}  $spec
     */
    private function seedCompany(int $companyId, array $spec): void
    {
        $areaIds = [];
        foreach ($spec['areas'] as $code => $name) {
            $areaIds[$code] = $this->firstOrInsert('areas', ['company_id' => $companyId, 'code' => $code], ['name' => $name]);
        }

        $positionIds = [];
        foreach ($spec['positions'] as $code => [$name, $areaCode]) {
            $positionIds[$code] = $this->firstOrInsert(
                'positions',
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'area_id' => $areaIds[$areaCode]],
            );
        }

        $userIds = [];
        $employeeIds = [];
        $nationality = (int) DB::table('nationalities')->where('c_pais', 'MEX')->value('id');
        $frequency = (int) DB::table('payroll_frequencies')->where('code', 'quincenal')->value('id');

        foreach ($spec['employees'] as [$code, $nickname, $name, $lastName, $sex, $areaCode, $positionCode, $hired]) {
            $userIds[$nickname] = $this->firstOrInsert(
                'users',
                ['company_id' => $companyId, 'nickname' => $nickname],
                [
                    'role_id' => self::ROLE_GENERAL,
                    'name' => $name,
                    'last_name' => $lastName,
                    'phone' => '871'.random_int(1000000, 9999999),
                    // Contraseña aleatoria: estos usuarios de demostración no inician sesión.
                    'passwd' => Hash::make(Str::random(40)),
                    'is_active' => 1,
                    'is_admin' => 0,
                ],
            );

            $employeeIds[$code] = $this->firstOrInsert(
                'employees',
                ['id_user' => $userIds[$nickname]],
                [
                    'nationality_id' => $nationality,
                    'payroll_frequency_id' => $frequency,
                    'area_id' => $areaIds[$areaCode],
                    'position_id' => $positionIds[$positionCode],
                    'code' => $code,
                    'sex' => $sex === 'F' ? 'Femenino' : 'Masculino',
                    'hire_date' => $hired,
                    'status' => 'activo',
                ],
            );
        }

        $this->seedAttendances(array_values($employeeIds));

        foreach ($spec['leads'] as [$code, $fullName, $email, $phone, $priority, $stage, $source, $responsible]) {
            $this->firstOrInsert(
                'leads',
                ['company_id' => $companyId, 'code' => $code],
                [
                    'lead_source_id' => (int) DB::table('lead_sources')->where('code', $source)->value('id'),
                    'lead_stage_id' => (int) DB::table('lead_stages')->where('code', $stage)->value('id'),
                    'responsible_user_id' => $userIds[$responsible],
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'priority' => $priority,
                ],
            );
        }
    }

    /**
     * Asistencias de los últimos días hábiles con un patrón determinista.
     *
     * @param  list<int>  $employeeIds
     */
    private function seedAttendances(array $employeeIds): void
    {
        $patterns = [
            ['presente', '08:58:00', '18:02:00', 0],
            ['presente', '08:55:00', '18:10:00', 0],
            ['retardo', '09:17:00', '18:05:00', 17],
            ['presente', '09:00:00', '18:00:00', 0],
            ['falta', null, null, 0],
        ];

        foreach ($employeeIds as $employeeIndex => $employeeId) {
            $count = 0;
            for ($daysAgo = 1; $count < 10; $daysAgo++) {
                $date = Carbon::today()->subDays($daysAgo);
                if ($date->isWeekend()) {
                    continue;
                }

                [$status, $in, $out, $late] = $patterns[($count + $employeeIndex) % count($patterns)];

                DB::table('attendances')->updateOrInsert(
                    ['employee_id' => $employeeId, 'attendance_date' => $date->toDateString()],
                    [
                        'expected_check_in' => '09:00:00',
                        'expected_check_out' => '18:00:00',
                        'check_in' => $in,
                        'check_out' => $out,
                        'late_minutes' => $late,
                        'overtime_hours' => $out === '18:10:00' ? '0.17' : '0.00',
                        'status' => $status,
                    ],
                );
                $count++;
            }
        }
    }

    /**
     * Devuelve el id de la fila que cumple $keys; si no existe, la crea con $values.
     *
     * @param  array<string, mixed>  $keys
     * @param  array<string, mixed>  $values
     */
    private function firstOrInsert(string $table, array $keys, array $values): int
    {
        $existing = DB::table($table)->where($keys)->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table($table)->insertGetId([...$keys, ...$values]);
    }
}
