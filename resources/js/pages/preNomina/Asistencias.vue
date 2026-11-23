<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    CalendarDays,
    Check,
    Clock3,
    Pencil,
    Save,
    Settings2,
    Users,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type DayKey =
    | 'lunes'
    | 'martes'
    | 'miércoles'
    | 'jueves'
    | 'viernes'
    | 'sábado'
    | 'domingo';
type AttendanceStatus = 'A tiempo' | 'Retardo' | 'Falta' | 'Descanso';
type Schedule = { enabled: boolean; start: string; end: string };
type Attendance = {
    id: number;
    employee: string;
    date: string;
    checkIn: string | null;
    checkOut: string | null;
    scheduleStart: string;
    scheduleEnd: string;
    delayMinutes: number;
    overtimeMinutes: number;
    status: AttendanceStatus;
};
type WorkSchedule = {
    id: number;
    day_of_week: number;
    start_time: string | null;
    end_time: string | null;
    is_day_off: boolean;
};
type EmployeeSchedule = {
    id: number;
    employee_number: string;
    first_name: string;
    last_name: string;
    work_schedules: WorkSchedule[];
};

const props = defineProps<{
    employees: EmployeeSchedule[];
    attendances: Attendance[];
    filters: { date: string; employee_id: number | null };
}>();

const days: { key: DayKey; label: string }[] = [
    { key: 'lunes', label: 'Lunes' },
    { key: 'martes', label: 'Martes' },
    { key: 'miércoles', label: 'Miércoles' },
    { key: 'jueves', label: 'Jueves' },
    { key: 'viernes', label: 'Viernes' },
    { key: 'sábado', label: 'Sábado' },
    { key: 'domingo', label: 'Domingo' },
];
const defaultSchedules: Record<DayKey, Schedule> = {
    lunes: { enabled: true, start: '09:00', end: '18:00' },
    martes: { enabled: true, start: '09:00', end: '18:00' },
    miércoles: { enabled: true, start: '09:00', end: '18:00' },
    jueves: { enabled: true, start: '09:00', end: '18:00' },
    viernes: { enabled: true, start: '09:00', end: '16:00' },
    sábado: { enabled: false, start: '09:00', end: '14:00' },
    domingo: { enabled: false, start: '09:00', end: '14:00' },
};
const defaultAttendance: Attendance[] = [
    {
        id: 1,
        employee: 'Ana Sofía Martínez',
        date: '2026-11-10',
        checkIn: '08:54',
        checkOut: '18:12',
        scheduleStart: '09:00',
        scheduleEnd: '18:00',
        delayMinutes: 0,
        overtimeMinutes: 12,
        status: 'A tiempo',
    },
    {
        id: 2,
        employee: 'Carlos Ramírez López',
        date: '2026-11-10',
        checkIn: '09:17',
        checkOut: '18:00',
        scheduleStart: '09:00',
        scheduleEnd: '18:00',
        delayMinutes: 17,
        overtimeMinutes: 0,
        status: 'Retardo',
    },
    {
        id: 3,
        employee: 'Mariana Torres García',
        date: '2026-11-10',
        checkIn: null,
        checkOut: null,
        scheduleStart: '09:00',
        scheduleEnd: '18:00',
        delayMinutes: 0,
        overtimeMinutes: 0,
        status: 'Falta',
    },
    {
        id: 4,
        employee: 'Diego Hernández Cruz',
        date: '2026-11-10',
        checkIn: '08:59',
        checkOut: '18:36',
        scheduleStart: '09:00',
        scheduleEnd: '18:00',
        delayMinutes: 0,
        overtimeMinutes: 36,
        status: 'A tiempo',
    },
];

const activeTab = ref<'attendance' | 'schedules'>('attendance');
const selectedDate = ref(props.filters.date);
const employeeFilter = ref<number | 'all'>(props.filters.employee_id ?? 'all');
const statusFilter = ref('Todos los estados');
const schedules = ref<Record<DayKey, Schedule>>(
    structuredClone(defaultSchedules),
);
const attendance = ref<Attendance[]>(props.attendances);
const editing = ref<Attendance | null>(null);
const editingSchedule = ref<WorkSchedule | null>(null);
const savedMessage = ref('');
const selectedScheduleEmployeeId = ref<number | null>(
    props.employees[0]?.id ?? null,
);
const selectedScheduleDay = ref(1);
const employees = computed(() => [
    ...new Set(attendance.value.map((row) => row.employee)),
]);
const selectedScheduleEmployee = computed(() =>
    props.employees.find(
        (employee) => employee.id === selectedScheduleEmployeeId.value,
    ),
);
const selectedEmployeeSchedules = computed(
    () =>
        new Map(
            selectedScheduleEmployee.value?.work_schedules.map((schedule) => [
                schedule.day_of_week,
                schedule,
            ]) ?? [],
        ),
);
const selectedSchedule = computed(() =>
    selectedEmployeeSchedules.value.get(selectedScheduleDay.value),
);
const filteredAttendance = computed(() =>
    attendance.value.filter(
        (row) =>
            statusFilter.value === 'Todos los estados' ||
            row.status === statusFilter.value,
    ),
);
const summary = computed(() => ({
    present: filteredAttendance.value.filter((row) => row.status !== 'Falta')
        .length,
    delays: filteredAttendance.value.filter((row) => row.delayMinutes > 0)
        .length,
    overtime: filteredAttendance.value.filter((row) => row.overtimeMinutes > 0)
        .length,
    absences: filteredAttendance.value.filter((row) => row.status === 'Falta')
        .length,
}));
const minutesBetween = (from: string, to: string): number => {
    const [fh, fm] = from.split(':').map(Number);
    const [th, tm] = to.split(':').map(Number);
    return th * 60 + tm - (fh * 60 + fm);
};
const formatMinutes = (minutes: number): string =>
    minutes > 0 ? `${minutes} min` : '—';
const statusTones: Record<
    AttendanceStatus,
    'green' | 'amber' | 'red' | 'slate'
> = {
    'A tiempo': 'green',
    Retardo: 'amber',
    Falta: 'red',
    Descanso: 'slate',
};
const statusTone = (
    status: AttendanceStatus,
): 'green' | 'amber' | 'red' | 'slate' => statusTones[status];
const openEditor = (row: Attendance): void => {
    router.visit(`/pre-nomina/asistencias/${row.id}`);
};
const updateCalculatedFields = (): void => {
    if (!editing.value) return;
    const row = editing.value;
    row.delayMinutes = row.checkIn
        ? Math.max(0, minutesBetween(row.scheduleStart, row.checkIn))
        : 0;
    row.overtimeMinutes = row.checkOut
        ? Math.max(0, minutesBetween(row.scheduleEnd, row.checkOut))
        : 0;
    row.status = row.checkIn
        ? row.delayMinutes > 0
            ? 'Retardo'
            : 'A tiempo'
        : 'Falta';
};
const saveCorrection = (): void => {
    if (!editing.value) return;
    router.put(
        `/pre-nomina/asistencias/${editing.value.id}`,
        {
            check_in: editing.value.checkIn || null,
            check_out: editing.value.checkOut || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                savedMessage.value = 'Corrección guardada';
                editing.value = null;
            },
        },
    );
};
const saveSchedules = (): void => {
    localStorage.setItem('nexus-schedules', JSON.stringify(schedules.value));
    savedMessage.value = 'Horarios guardados';
    window.setTimeout(() => {
        savedMessage.value = '';
    }, 2500);
};
const openScheduleEditor = (schedule: WorkSchedule): void => {
    editingSchedule.value = {
        ...schedule,
        start_time: schedule.start_time?.slice(0, 5) ?? null,
        end_time: schedule.end_time?.slice(0, 5) ?? null,
    };
    savedMessage.value = '';
};
const openSelectedScheduleEditor = (): void => {
    if (selectedSchedule.value) openScheduleEditor(selectedSchedule.value);
};
const saveSchedule = (): void => {
    if (!editingSchedule.value || selectedScheduleEmployeeId.value === null)
        return;
    router.put(
        `/pre-nomina/empleados/${selectedScheduleEmployeeId.value}/horarios/${editingSchedule.value.id}`,
        {
            day_of_week: editingSchedule.value.day_of_week,
            start_time: editingSchedule.value.start_time,
            end_time: editingSchedule.value.end_time,
            is_day_off: editingSchedule.value.is_day_off,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                savedMessage.value = 'Horario actualizado';
                editingSchedule.value = null;
            },
        },
    );
};
const applyAttendanceFilters = (): void => {
    router.get(
        '/pre-nomina/asistencias',
        {
            date: selectedDate.value,
            employee_id:
                employeeFilter.value === 'all'
                    ? undefined
                    : employeeFilter.value,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};
onMounted(() => {
    const storedSchedules = localStorage.getItem('nexus-schedules');
    if (storedSchedules)
        schedules.value = {
            ...structuredClone(defaultSchedules),
            ...JSON.parse(storedSchedules),
        };
});
watch(
    () => props.attendances,
    (records) => {
        attendance.value = records;
    },
);
</script>

<template>
    <Head title="Asistencias" />
    <NexusPageHeader
        eyebrow="PRE-NÓMINA / CONTROL DE TIEMPO"
        title="Asistencias y horarios"
        description="Configura la jornada esperada y compara cada registro contra la entrada y salida reales."
    >
        <template #action
            ><div
                v-if="savedMessage"
                class="flex items-center gap-2 rounded-lg bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-700"
            >
                <Check class="size-4" /> {{ savedMessage }}
            </div></template
        >
    </NexusPageHeader>
    <div class="mb-6 flex items-center gap-1 border-b border-slate-200">
        <button
            type="button"
            class="flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-semibold transition"
            :class="
                activeTab === 'attendance'
                    ? 'border-[#23c6c8] text-slate-900'
                    : 'border-transparent text-slate-500 hover:text-slate-800'
            "
            @click="activeTab = 'attendance'"
        >
            <Clock3 class="size-4" /> Asistencia diaria
        </button>
        <button
            type="button"
            class="flex items-center gap-2 border-b-2 px-4 py-3 text-xs font-semibold transition"
            :class="
                activeTab === 'schedules'
                    ? 'border-[#23c6c8] text-slate-900'
                    : 'border-transparent text-slate-500 hover:text-slate-800'
            "
            @click="activeTab = 'schedules'"
        >
            <Settings2 class="size-4" /> Horarios de trabajo
        </button>
    </div>
    <template v-if="activeTab === 'attendance'">
        <section class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="card in [
                    {
                        label: 'Personal con registro',
                        value: summary.present,
                        icon: Users,
                        tone: 'text-[#139b9e]',
                    },
                    {
                        label: 'Retardos',
                        value: summary.delays,
                        icon: Clock3,
                        tone: 'text-amber-600',
                    },
                    {
                        label: 'Horas extra',
                        value: summary.overtime,
                        icon: CalendarDays,
                        tone: 'text-blue-600',
                    },
                    {
                        label: 'Faltas',
                        value: summary.absences,
                        icon: X,
                        tone: 'text-rose-600',
                    },
                ]"
                :key="card.label"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/30"
            >
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-medium text-slate-500">
                        {{ card.label }}
                    </p>
                    <component
                        :is="card.icon"
                        class="size-4"
                        :class="card.tone"
                    />
                </div>
                <p
                    class="mt-2 text-2xl font-semibold tracking-tight text-slate-900"
                >
                    {{ card.value }}
                </p>
            </div>
        </section>
        <section
            class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/30"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-100 p-4 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Registro diario
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Los minutos se calculan a partir del horario asignado.
                    </p>
                </div>
                <div class="grid gap-2 sm:grid-cols-3">
                    <label
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >Fecha<input
                            v-model="selectedDate"
                            type="date"
                            class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs text-slate-700 outline-none focus:border-[#23c6c8]"
                            @change="applyAttendanceFilters" /></label
                    ><label
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >Empleado<select
                            v-model="employeeFilter"
                            class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs text-slate-700 outline-none focus:border-[#23c6c8]"
                            @change="applyAttendanceFilters"
                        >
                            <option value="all">Todos los empleados</option>
                            <option
                                v-for="employee in props.employees"
                                :key="employee.id"
                                :value="employee.id"
                            >
                                {{ employee.first_name }}
                                {{ employee.last_name }}
                            </option>
                        </select></label
                    ><label
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >Estado<select
                            v-model="statusFilter"
                            class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs text-slate-700 outline-none focus:border-[#23c6c8]"
                        >
                            <option>Todos los estados</option>
                            <option>A tiempo</option>
                            <option>Retardo</option>
                            <option>Falta</option>
                            <option>Descanso</option>
                        </select></label
                    >
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead
                        class="bg-slate-50 text-[10px] tracking-wide text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3 font-semibold">Empleado</th>
                            <th class="px-4 py-3 font-semibold">
                                Horario esperado
                            </th>
                            <th class="px-4 py-3 font-semibold">
                                Entrada real
                            </th>
                            <th class="px-4 py-3 font-semibold">Salida real</th>
                            <th class="px-4 py-3 font-semibold">Retardo</th>
                            <th class="px-4 py-3 font-semibold">Horas extra</th>
                            <th class="px-4 py-3 font-semibold">Estado</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in filteredAttendance"
                            :key="row.id"
                            class="hover:bg-slate-50/70"
                        >
                            <td class="px-4 py-3 font-semibold text-slate-800">
                                {{ row.employee }}
                            </td>
                            <td class="px-4 py-3 text-slate-500">
                                {{ row.scheduleStart }} — {{ row.scheduleEnd }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-700">
                                {{ row.checkIn || '—' }}
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-700">
                                {{ row.checkOut || '—' }}
                            </td>
                            <td
                                class="px-4 py-3"
                                :class="
                                    row.delayMinutes
                                        ? 'font-semibold text-amber-700'
                                        : 'text-slate-400'
                                "
                            >
                                {{ formatMinutes(row.delayMinutes) }}
                            </td>
                            <td
                                class="px-4 py-3"
                                :class="
                                    row.overtimeMinutes
                                        ? 'font-semibold text-blue-700'
                                        : 'text-slate-400'
                                "
                            >
                                {{ formatMinutes(row.overtimeMinutes) }}
                            </td>
                            <td class="px-4 py-3">
                                <NexusBadge
                                    :label="row.status"
                                    :tone="statusTone(row.status)"
                                />
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-[11px] font-semibold text-slate-600 hover:bg-slate-100"
                                    @click="openEditor(row)"
                                >
                                    <Pencil class="size-3.5" /> Corregir
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!filteredAttendance.length">
                            <td
                                colspan="8"
                                class="px-4 py-12 text-center text-sm text-slate-500"
                            >
                                No hay registros para los filtros seleccionados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </template>
    <template v-else>
        <section
            class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/30"
        >
            <div
                class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        Jornada semanal
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        Horarios registrados para el cálculo de asistencias.
                    </p>
                </div>
                <label
                    class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >Empleado<select
                        v-model="selectedScheduleEmployeeId"
                        class="mt-1 block h-9 min-w-64 rounded-lg border border-slate-200 px-2 text-xs text-slate-700 outline-none focus:border-[#23c6c8]"
                    >
                        <option
                            v-for="employee in props.employees"
                            :key="employee.id"
                            :value="employee.id"
                        >
                            {{ employee.first_name }} {{ employee.last_name }} —
                            {{ employee.employee_number }}
                        </option>
                    </select></label
                >
            </div>
            <div class="divide-y divide-slate-100">
                <div
                    v-for="(day, index) in days"
                    :key="day.key"
                    class="grid items-center gap-4 px-4 py-4 sm:grid-cols-[150px_120px_1fr_1fr]"
                    :class="
                        selectedEmployeeSchedules.get(index + 1)?.is_day_off
                            ? 'bg-slate-50/70'
                            : ''
                    "
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-8 items-center justify-center rounded-lg"
                            :class="
                                selectedEmployeeSchedules.get(index + 1)
                                    ?.is_day_off
                                    ? 'bg-slate-100 text-slate-400'
                                    : 'bg-cyan-50 text-[#139b9e]'
                            "
                            ><CalendarDays class="size-4" /></span
                        ><span class="text-xs font-semibold text-slate-800">{{
                            day.label
                        }}</span>
                    </div>
                    <span
                        class="text-xs font-medium"
                        :class="
                            selectedEmployeeSchedules.get(index + 1)?.is_day_off
                                ? 'text-slate-500'
                                : 'text-emerald-700'
                        "
                        >{{
                            selectedEmployeeSchedules.get(index + 1)?.is_day_off
                                ? 'Día de descanso'
                                : 'Laborable'
                        }}</span
                    >
                    <div>
                        <p
                            class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            Entrada esperada
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-700">
                            {{
                                selectedEmployeeSchedules
                                    .get(index + 1)
                                    ?.start_time?.slice(0, 5) || '—'
                            }}
                        </p>
                    </div>
                    <div>
                        <p
                            class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >
                            Salida esperada
                        </p>
                        <p class="mt-1 text-sm font-medium text-slate-700">
                            {{
                                selectedEmployeeSchedules
                                    .get(index + 1)
                                    ?.end_time?.slice(0, 5) || '—'
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
        <p class="mt-3 text-[11px] text-slate-500">
            Los horarios se están consultando desde la base de datos. La edición
            y el guardado se agregarán en el siguiente subpaso.
        </p>
    </template>
    <div
        v-if="activeTab === 'schedules'"
        class="mt-4 flex flex-wrap items-end justify-end gap-2"
    >
        <label
            class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
            >Día a editar<select
                v-model="selectedScheduleDay"
                class="mt-1 block h-9 rounded-lg border border-slate-200 px-2 text-xs text-slate-700 outline-none focus:border-[#23c6c8]"
            >
                <option
                    v-for="(day, index) in days"
                    :key="day.key"
                    :value="index + 1"
                >
                    {{ day.label }}
                </option>
            </select></label
        ><button
            type="button"
            class="h-9 rounded-lg bg-[#0a161e] px-3 text-xs font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!selectedSchedule"
            @click="openSelectedScheduleEditor"
        >
            Editar día seleccionado
        </button>
    </div>
    <div
        v-if="editingSchedule"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4"
        @click.self="editingSchedule = null"
    >
        <section class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <p
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Editar horario
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ days[editingSchedule.day_of_week - 1]?.label }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ selectedScheduleEmployee?.first_name }}
                        {{ selectedScheduleEmployee?.last_name }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100"
                    aria-label="Cerrar"
                    @click="editingSchedule = null"
                >
                    <X class="size-4" />
                </button>
            </div>
            <label
                class="mt-5 flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-3 text-xs font-medium text-slate-700"
                ><input
                    v-model="editingSchedule.is_day_off"
                    type="checkbox"
                    class="size-4 accent-[#23c6c8]"
                />
                Marcar como día de descanso</label
            >
            <div class="mt-4 grid grid-cols-2 gap-3">
                <label
                    class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    :class="editingSchedule.is_day_off ? 'opacity-50' : ''"
                    >Entrada<input
                        v-model="editingSchedule.start_time"
                        type="time"
                        :disabled="editingSchedule.is_day_off"
                        class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8] disabled:bg-slate-100" /></label
                ><label
                    class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    :class="editingSchedule.is_day_off ? 'opacity-50' : ''"
                    >Salida<input
                        v-model="editingSchedule.end_time"
                        type="time"
                        :disabled="editingSchedule.is_day_off"
                        class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8] disabled:bg-slate-100"
                /></label>
            </div>
            <p
                class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500"
            >
                Un día laborable requiere entrada y salida; la salida debe ser
                posterior a la entrada.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                    @click="editingSchedule = null"
                >
                    Cancelar</button
                ><button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#0a161e] px-3 py-2 text-xs font-semibold text-white hover:bg-[#162732]"
                    @click="saveSchedule"
                >
                    <Save class="size-3.5" /> Guardar horario
                </button>
            </div>
        </section>
    </div>
    <div
        v-if="editing"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4"
        @click.self="editing = null"
    >
        <section class="w-full max-w-md rounded-xl bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between">
                <div>
                    <p
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Corrección manual
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ editing.employee }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ editing.date }}
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-md p-1 text-slate-400 hover:bg-slate-100"
                    aria-label="Cerrar"
                    @click="editing = null"
                >
                    <X class="size-4" />
                </button>
            </div>
            <div class="mt-5 grid grid-cols-2 gap-3">
                <label
                    class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >Entrada real<input
                        v-model="editing.checkIn"
                        type="time"
                        class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8]" /></label
                ><label
                    class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >Salida real<input
                        v-model="editing.checkOut"
                        type="time"
                        class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8]"
                /></label>
            </div>
            <p
                class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500"
            >
                El retardo y las horas extra se recalculan automáticamente al
                guardar.
            </p>
            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                    @click="editing = null"
                >
                    Cancelar</button
                ><button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#0a161e] px-3 py-2 text-xs font-semibold text-white hover:bg-[#162732]"
                    @click="saveCorrection"
                >
                    <Save class="size-3.5" /> Guardar corrección
                </button>
            </div>
        </section>
    </div>
</template>
