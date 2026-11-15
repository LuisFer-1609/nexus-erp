# SP/02 — Auditoría del modelo de datos existente

## Fecha

15 de noviembre de 2026

## Objetivo

Revisar las migraciones, modelos, seeders y rutas existentes antes de crear la funcionalidad de horarios y asistencias, evitando duplicar estructuras del proyecto.

## Resultado

Actualmente el proyecto no tiene entidades persistentes para empleados, horarios ni asistencias.

### Migraciones encontradas

- `create_users_table`
- `create_cache_table`
- `create_jobs_table`
- `create_passkeys_table`
- `add_two_factor_columns_to_users_table`

### Modelos encontrados

- `App\Models\User`

### Seeders y factories encontrados

- `DatabaseSeeder`
- `UserFactory`

No se encontraron tablas, modelos o seeders relacionados con:

- empleados;
- horarios de trabajo;
- asistencias;
- retardos;
- horas extra.

## Decisión técnica

Se crearán nuevas estructuras para el módulo SP/02 sin modificar ni duplicar entidades existentes:

1. `employees` para empleados de prueba.
2. `work_schedules` para la jornada esperada por empleado y día de la semana.
3. `attendances` para la entrada y salida reales de cada empleado por fecha.

La vista actual `resources/js/pages/preNomina/Asistencias.vue` contiene únicamente datos simulados para prototipo y persistencia en `localStorage`; será conectada posteriormente con los modelos y controladores de Laravel.

## Estado del checklist

**Revisar qué tablas y modelos ya existen:** completado.
