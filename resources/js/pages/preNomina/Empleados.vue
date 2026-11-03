<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, UserCheck, UserMinus, UserPlus } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const employees = [
    { initials: 'DO', name: 'Daniel Ortiz', area: 'Ventas · Ejecutivo', contact: '871 555 0110', date: '12 ene 2025', status: 'Activo', schedule: '08:00–17:00' },
    { initials: 'FC', name: 'Fernanda Cruz', area: 'RH · Generalista', contact: '871 555 0118', date: '03 mar 2024', status: 'Activo', schedule: '08:00–17:00' },
    { initials: 'JM', name: 'Jorge Medina', area: 'Ventas · Ejecutivo', contact: '871 555 0126', date: '20 jun 2025', status: 'Activo', schedule: '08:00–17:00' },
    { initials: 'AS', name: 'Andrea Salas', area: 'Marketing · Analista', contact: '871 555 0132', date: '08 nov 2024', status: 'Activo', schedule: '08:00–17:00' },
    { initials: 'RP', name: 'Raúl Pérez', area: 'Operaciones · Supervisor', contact: '871 555 0141', date: '01 feb 2023', status: 'Activo', schedule: '07:00–16:00' },
];
</script>

<template>
    <Head title="Empleados" />

    <NexusPageHeader
        eyebrow="PRE-NÓMINA / Empleados"
        title="Empleados"
        description="Gestiona el personal registrado y su información laboral."
    >
        <template #action>
            <button class="h-9 rounded-lg bg-[#139b9e] px-4 text-[11px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">
                + Nuevo empleado
            </button>
        </template>
    </NexusPageHeader>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <NexusKpiCard label="Empleados activos" value="29" helper="Actualizado" :icon="UserCheck" />
        <NexusKpiCard label="Inactivos" value="3" helper="Sin acceso" :icon="UserMinus" tone="violet" />
        <NexusKpiCard label="Con incidencias" value="5" helper="Revisar" :icon="AlertTriangle" tone="amber" />
        <NexusKpiCard label="Nuevos este mes" value="2" helper="Altas recientes" :icon="UserPlus" tone="green" />
    </section>

    <section class="mt-5 rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="grid gap-3 md:grid-cols-[1.6fr_1fr_1fr_auto]">
            <input class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[11px] outline-none transition focus:border-[#23c6c8] focus:bg-white" placeholder="Buscar empleado, puesto o área..." />
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none"><option>Área: Todas</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none"><option>Estado: Activos</option></select>
            <button class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:border-[#23c6c8]/50 hover:bg-cyan-50/30 hover:text-[#0e7f82]">Exportar</button>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Directorio de empleados</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Empleado</th>
                        <th class="px-4 py-3">Área / puesto</th>
                        <th class="px-4 py-3">Contacto</th>
                        <th class="px-4 py-3">Ingreso</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Horario</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="employee in employees" :key="employee.name" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-[9px] font-semibold text-[#139b9e]">{{ employee.initials }}</div>
                                <p class="text-[11px] font-semibold text-slate-900">{{ employee.name }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ employee.area }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-600">{{ employee.contact }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-500">{{ employee.date }}</td>
                        <td class="px-4 py-3.5"><NexusBadge :label="employee.status" tone="green" /></td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ employee.schedule }}</td>
                        <td class="px-5 py-3.5 text-right"><button class="text-[10px] font-semibold text-[#139b9e] transition hover:text-[#0e7f82]">Editar</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
