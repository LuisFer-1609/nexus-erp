<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Clock3, TimerReset, UserCheck, UserX } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const rows = [
    { initials: 'DO', name: 'Daniel Ortiz', area: 'Ventas', entry: '08:01', exit: '17:12', delay: '—', extra: '0.5 h', status: 'Presente', tone: 'green' },
    { initials: 'FC', name: 'Fernanda Cruz', area: 'RH', entry: '08:18', exit: '17:03', delay: '18 min', extra: '—', status: 'Retardo', tone: 'amber' },
    { initials: 'JM', name: 'Jorge Medina', area: 'Ventas', entry: '—', exit: '—', delay: '—', extra: '—', status: 'Falta', tone: 'red' },
    { initials: 'AS', name: 'Andrea Salas', area: 'Marketing', entry: '07:58', exit: '18:30', delay: '—', extra: '1.5 h', status: 'Presente', tone: 'green' },
    { initials: 'RP', name: 'Raúl Pérez', area: 'Operaciones', entry: '08:05', exit: '17:10', delay: '5 min', extra: '—', status: 'Retardo', tone: 'amber' },
] as const;
</script>

<template>
    <Head title="Asistencias" />

    <NexusPageHeader
        eyebrow="Recursos Humanos / Asistencias"
        title="Control de asistencias"
        description="Registro diario de entradas, salidas, retardos, faltas y horas extra."
    />

    <section class="rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="grid gap-3 md:grid-cols-[1fr_auto_auto_1.3fr_auto]">
            <label>
                <span class="mb-1.5 block text-[9px] font-medium text-slate-400">Fecha</span>
                <input type="text" value="28 septiembre 2026" class="h-9 w-full rounded-lg border border-slate-200 px-3 text-[11px] text-slate-700 outline-none focus:border-[#23c6c8]" />
            </label>
            <button class="mt-auto size-9 rounded-lg border border-slate-200 text-sm text-slate-500 transition hover:bg-slate-50">‹</button>
            <button class="mt-auto size-9 rounded-lg border border-slate-200 text-sm text-slate-500 transition hover:bg-slate-50">›</button>
            <input class="mt-auto h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[11px] outline-none focus:border-[#23c6c8] focus:bg-white" placeholder="Buscar empleado..." />
            <button class="mt-auto h-9 rounded-lg bg-[#139b9e] px-4 text-[10px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">+ Registrar incidencia o asistencia</button>
        </div>
    </section>

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <NexusKpiCard label="Presentes" value="24" helper="82% del equipo" :icon="UserCheck" />
        <NexusKpiCard label="Retardos" value="3" helper="2 corregibles" :icon="Clock3" tone="amber" />
        <NexusKpiCard label="Faltas" value="2" helper="Sin justificar" :icon="UserX" tone="violet" />
        <NexusKpiCard label="Horas extra" value="7.5 h" helper="Acumuladas hoy" :icon="TimerReset" tone="green" />
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Asistencia del día</h2>
            <p class="text-[10px] text-slate-500">29 empleados activos</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1000px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Empleado</th><th class="px-4 py-3">Área</th><th class="px-4 py-3">Entrada</th><th class="px-4 py-3">Salida</th>
                        <th class="px-4 py-3">Retardo</th><th class="px-4 py-3">H. extra</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in rows" :key="row.name" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-[9px] font-semibold text-[#139b9e]">{{ row.initials }}</div><p class="text-[11px] font-semibold text-slate-900">{{ row.name }}</p></div></td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.area }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.entry }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.exit }}</td>
                        <td class="px-4 py-3.5 text-[10px]" :class="row.delay !== '—' ? 'font-medium text-amber-600' : 'text-slate-400'">{{ row.delay }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-600">{{ row.extra }}</td>
                        <td class="px-4 py-3.5"><NexusBadge :label="row.status" :tone="row.tone" /></td>
                        <td class="px-5 py-3.5"><button class="text-[10px] font-semibold text-[#139b9e] transition hover:text-[#0e7f82]">Editar</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
