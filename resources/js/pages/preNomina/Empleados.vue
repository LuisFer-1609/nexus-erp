<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Clock3,
    FileSpreadsheet,
    Pencil,
    Plus,
    Search,
    Trash2,
} from '@lucide/vue';
import { ElOption, ElSelect } from 'element-plus';
import { computed, ref } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmployeeCsvModal from '@/components/nexus/NexusEmployeeCsvModal.vue';
import NexusEmployeeModal, {
    type EditableEmployee,
    type EmployeeCatalogs,
} from '@/components/nexus/NexusEmployeeModal.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import { asistencias } from '@/routes/pre-nomina';
import { destroy as destroyEmpleado } from '@/routes/pre-nomina/empleados';

type EmployeeRow = EditableEmployee & {
    initials: string;
    name: string;
    area: string;
    role: string;
    contact: string;
    date: string;
    status: string;
    frequency_name: string;
    nationality_name: string;
    schedule: string;
    attendance_stats: {
        attended: number;
        delays: number;
        absences: number;
        incidents: number;
    };
};

const props = withDefaults(
    defineProps<{
        employees?: EmployeeRow[];
        catalogs?: EmployeeCatalogs;
    }>(),
    {
        employees: () => [],
        catalogs: () => ({
            areas: [],
            positions: [],
            nationalities: [],
            frequencies: [],
            roles: [],
        }),
    },
);

const searchQuery = ref('');
const selectedArea = ref('Todas');
const selectedStatus = ref<'Todos' | 'Activo' | 'Inactivo'>('Todos');
const modalOpen = ref(false);
const csvModalOpen = ref(false);
const editingEmployee = ref<EditableEmployee | null>(null);
const deletingEmployee = ref<EmployeeRow | null>(null);
const deleteBusy = ref(false);

const filteredEmployees = computed(() =>
    props.employees.filter((emp) => {
        const matchesArea =
            selectedArea.value === 'Todas' || emp.area === selectedArea.value;
        const matchesStatus =
            selectedStatus.value === 'Todos' ||
            emp.status === selectedStatus.value;
        const q = searchQuery.value.trim().toLowerCase();
        const matchesQuery =
            !q ||
            emp.name.toLowerCase().includes(q) ||
            emp.code.toLowerCase().includes(q) ||
            emp.curp.toLowerCase().includes(q) ||
            emp.area.toLowerCase().includes(q) ||
            emp.role.toLowerCase().includes(q);

        return matchesArea && matchesStatus && matchesQuery;
    }),
);

const totals = computed(() => ({
    total: props.employees.length,
    active: props.employees.filter((e) => e.raw_status === 'activo').length,
    attended: props.employees.reduce(
        (acc, e) => acc + e.attendance_stats.attended,
        0,
    ),
    absences: props.employees.reduce(
        (acc, e) => acc + e.attendance_stats.absences,
        0,
    ),
}));

const openCreate = (): void => {
    editingEmployee.value = null;
    modalOpen.value = true;
};

const openEdit = (employee: EmployeeRow): void => {
    editingEmployee.value = employee;
    modalOpen.value = true;
};

const confirmDelete = (): void => {
    if (!deletingEmployee.value) {
        return;
    }

    deleteBusy.value = true;
    router.delete(destroyEmpleado.url(deletingEmployee.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            deletingEmployee.value = null;
        },
        onFinish: () => {
            deleteBusy.value = false;
        },
    });
};
</script>

<template>
    <div class="space-y-5">
        <Head title="Empleados" />

        <NexusPageHeader
            eyebrow="PRE-NÓMINA / Empleados"
            title="Empleados"
            description="Administra el personal, sus catálogos laborales, importación por CSV y relación con asistencias y faltas."
        >
            <template #action>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                        @click="csvModalOpen = true"
                    >
                        <FileSpreadsheet class="size-4" aria-hidden="true" />
                        Importar CSV
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1bb2b4] focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                        @click="openCreate"
                    >
                        <Plus class="size-4" aria-hidden="true" />
                        Nuevo empleado
                    </button>
                </div>
            </template>
        </NexusPageHeader>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
                class="rounded-[12px] border border-slate-200/90 bg-white px-4 py-3.5"
            >
                <p class="text-xs font-medium text-slate-500">
                    Total empleados
                </p>
                <p
                    class="mt-1 text-[26px] font-semibold text-slate-900 tabular-nums"
                >
                    {{ totals.total }}
                </p>
            </div>
            <div
                class="rounded-[12px] border border-slate-200/90 bg-white px-4 py-3.5"
            >
                <p class="text-xs font-medium text-slate-500">Activos</p>
                <p
                    class="mt-1 text-[26px] font-semibold text-emerald-700 tabular-nums"
                >
                    {{ totals.active }}
                </p>
            </div>
            <div
                class="rounded-[12px] border border-slate-200/90 bg-white px-4 py-3.5"
            >
                <p class="text-xs font-medium text-slate-500">
                    Asistencias registradas
                </p>
                <p
                    class="mt-1 text-[26px] font-semibold text-[#139b9e] tabular-nums"
                >
                    {{ totals.attended }}
                </p>
            </div>
            <div
                class="rounded-[12px] border border-slate-200/90 bg-white px-4 py-3.5"
            >
                <p class="text-xs font-medium text-slate-500">
                    Faltas registradas
                </p>
                <p
                    class="mt-1 text-[26px] font-semibold text-amber-600 tabular-nums"
                >
                    {{ totals.absences }}
                </p>
            </div>
        </section>

        <section class="rounded-[12px] border border-slate-200/90 bg-white p-4">
            <div class="grid gap-3 md:grid-cols-[1.6fr_1fr_0.8fr]">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs transition outline-none focus:border-[#23c6c8] focus:bg-white"
                        placeholder="Buscar por nombre, clave, CURP, área o puesto..."
                    />
                </div>
                <ElSelect
                    v-model="selectedArea"
                    placeholder="Filtrar por área"
                    class="w-full"
                >
                    <ElOption label="Área: Todas" value="Todas" />
                    <ElOption
                        v-for="area in catalogs.areas"
                        :key="area.id"
                        :label="area.name"
                        :value="area.name"
                    />
                </ElSelect>
                <ElSelect
                    v-model="selectedStatus"
                    placeholder="Estado"
                    class="w-full"
                >
                    <ElOption label="Estado: Todos" value="Todos" />
                    <ElOption label="Activos" value="Activo" />
                    <ElOption label="Inactivos" value="Inactivo" />
                </ElSelect>
            </div>
        </section>

        <NexusEmptyState
            v-if="filteredEmployees.length === 0"
            description="No hay empleados registrados que coincidan con el filtro."
        />

        <section
            v-else
            class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
            >
                <h2 class="text-sm font-semibold text-slate-900">
                    Directorio de empleados
                </h2>
                <span class="text-xs text-slate-500 tabular-nums">
                    {{ filteredEmployees.length }} de {{ employees.length }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1040px] text-left">
                    <thead
                        class="bg-slate-50 text-xs font-semibold tracking-[0.05em] text-slate-500 uppercase"
                    >
                        <tr>
                            <th class="px-5 py-3">Empleado</th>
                            <th class="px-4 py-3">Área / puesto</th>
                            <th class="px-4 py-3">CURP / NSS</th>
                            <th class="px-4 py-3">Nómina / ingreso</th>
                            <th class="px-4 py-3">Asistencias / faltas</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="employee in filteredEmployees"
                            :key="employee.id"
                            class="transition-colors hover:bg-slate-50/80"
                        >
                            <td class="px-5 py-3.5">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-xs font-semibold text-[#139b9e]"
                                    >
                                        {{ employee.initials }}
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-slate-900"
                                        >
                                            {{ employee.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ employee.code || 'Sin clave' }} ·
                                            {{ employee.contact }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-700">
                                <p class="font-medium text-slate-800">
                                    {{ employee.area }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ employee.role }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3.5 font-mono text-[11px] text-slate-600"
                            >
                                <p>{{ employee.curp || '—' }}</p>
                                <p class="text-slate-400">
                                    NSS: {{ employee.ssn || '—' }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-600">
                                <p class="font-medium text-slate-800">
                                    {{ employee.frequency_name || '—' }}
                                </p>
                                <p class="text-[11px] text-slate-500">
                                    {{ employee.date }}
                                </p>
                            </td>
                            <td class="px-4 py-3.5 text-xs">
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <NexusBadge
                                        :label="`${employee.attendance_stats.attended} asist.`"
                                        tone="cyan"
                                    />
                                    <NexusBadge
                                        v-if="
                                            employee.attendance_stats.delays > 0
                                        "
                                        :label="`${employee.attendance_stats.delays} ret.`"
                                        tone="amber"
                                    />
                                    <NexusBadge
                                        v-if="
                                            employee.attendance_stats.absences >
                                            0
                                        "
                                        :label="`${employee.attendance_stats.absences} faltas`"
                                        tone="red"
                                    />
                                </div>
                                <Link
                                    :href="
                                        asistencias.url({
                                            query: { employee_id: employee.id },
                                        })
                                    "
                                    class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-[#139b9e] hover:underline"
                                >
                                    <Clock3 class="size-3" />
                                    {{ employee.schedule }}
                                </Link>
                            </td>
                            <td class="px-4 py-3.5">
                                <NexusBadge
                                    :label="employee.status"
                                    :tone="
                                        employee.raw_status === 'activo'
                                            ? 'green'
                                            : 'slate'
                                    "
                                />
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <div class="inline-flex items-center gap-1">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                                        aria-label="Editar empleado"
                                        @click="openEdit(employee)"
                                    >
                                        <Pencil class="size-3.5" />
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-xs font-medium text-red-600 transition hover:bg-red-50"
                                        aria-label="Dar de baja empleado"
                                        @click="deletingEmployee = employee"
                                    >
                                        <Trash2 class="size-3.5" />
                                        Baja
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <NexusEmployeeModal
            :open="modalOpen"
            :employee="editingEmployee"
            :catalogs="catalogs"
            @close="modalOpen = false"
        />

        <NexusEmployeeCsvModal
            :open="csvModalOpen"
            @close="csvModalOpen = false"
        />

        <Teleport to="body">
            <div
                v-if="deletingEmployee"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-[2px]"
                @click.self="deletingEmployee = null"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl"
                >
                    <h2 class="text-base font-semibold text-slate-900">
                        Dar de baja empleado
                    </h2>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                        ¿Confirmas dar de baja a
                        <strong class="text-slate-900">{{
                            deletingEmployee.name
                        }}</strong
                        >? Su historial de asistencias y nóminas se conservará.
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="h-10 rounded-lg px-4 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            @click="deletingEmployee = null"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="deleteBusy"
                            class="h-10 rounded-lg bg-red-600 px-4 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            @click="confirmDelete"
                        >
                            {{
                                deleteBusy ? 'Procesando...' : 'Sí, dar de baja'
                            }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>
