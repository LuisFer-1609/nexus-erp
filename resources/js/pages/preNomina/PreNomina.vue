<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Banknote, CircleDollarSign, ReceiptText, Users } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const payroll = [
    { name: 'Daniel Ortiz', days: '10', delays: '0', absences: '0', extra: '2.5', gross: '$9,840', deductions: '$320', net: '$9,520', status: 'Correcto', tone: 'green' },
    { name: 'Fernanda Cruz', days: '9', delays: '2', absences: '0', extra: '0', gross: '$8,920', deductions: '$260', net: '$8,660', status: 'Revisar', tone: 'amber' },
    { name: 'Jorge Medina', days: '8', delays: '0', absences: '1', extra: '4', gross: '$8,410', deductions: '$780', net: '$7,630', status: 'Revisar', tone: 'amber' },
    { name: 'Andrea Salas', days: '10', delays: '0', absences: '0', extra: '0', gross: '$10,200', deductions: '$350', net: '$9,850', status: 'Correcto', tone: 'green' },
    { name: 'Raúl Pérez', days: '10', delays: '0', absences: '0', extra: '0', gross: '$9,650', deductions: '$290', net: '$9,360', status: 'Correcto', tone: 'green' },
] as const;
</script>

<template>
    <Head title="Pre-nómina" />

    <NexusPageHeader
        eyebrow="Recursos Humanos / Pre-nómina"
        title="Pre-nómina"
        description="Calcula percepciones y deducciones a partir de asistencias registradas."
    />

    <section class="rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="flex flex-wrap items-end gap-3">
                <label>
                    <span class="mb-1.5 block text-[9px] font-medium text-slate-400">Periodo</span>
                    <select class="h-9 min-w-56 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-slate-700 outline-none focus:border-[#23c6c8]">
                        <option>16 sept — 30 sept 2026</option>
                    </select>
                </label>
                <NexusBadge label="Borrador" tone="amber" />
            </div>

            <div class="flex gap-2">
                <button class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-[10px] font-semibold text-slate-700 transition hover:bg-slate-50">Recalcular</button>
                <button class="h-9 rounded-lg bg-[#139b9e] px-4 text-[10px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">Exportar reporte</button>
            </div>
        </div>
    </section>

    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <NexusKpiCard label="Empleados" value="18" helper="Periodo actual" :icon="Users" />
        <NexusKpiCard label="Percepciones" value="$164,820" helper="Bruto estimado" :icon="Banknote" tone="green" />
        <NexusKpiCard label="Deducciones" value="$12,460" helper="Incidencias" :icon="ReceiptText" tone="amber" />
        <NexusKpiCard label="Neto estimado" value="$152,360" helper="Antes de nómina final" :icon="CircleDollarSign" tone="violet" />
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="flex flex-col gap-1 border-b border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="text-sm font-semibold text-slate-900">Detalle por empleado</h2>
            <p class="text-[10px] text-slate-500">Los valores pueden ajustarse antes de exportar.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Empleado</th><th class="px-4 py-3">Días</th><th class="px-4 py-3">Ret.</th><th class="px-4 py-3">Faltas</th><th class="px-4 py-3">H. extra</th>
                        <th class="px-4 py-3">Bruto</th><th class="px-4 py-3">Deducciones</th><th class="px-4 py-3">Neto</th><th class="px-5 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="row in payroll" :key="row.name" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5 text-[11px] font-semibold text-slate-900">{{ row.name }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.days }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.delays }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.absences }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ row.extra }}</td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ row.gross }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-500">{{ row.deductions }}</td>
                        <td class="px-4 py-3.5 text-[10px] font-semibold text-slate-900">{{ row.net }}</td>
                        <td class="px-5 py-3.5"><NexusBadge :label="row.status" :tone="row.tone" /></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
