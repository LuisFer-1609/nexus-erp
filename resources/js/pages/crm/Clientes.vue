<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const clients = [
    { initials: 'ML', name: 'María López', email: 'maria.lopez@correo.mx', company: 'Studio Norte', origin: 'Facebook', owner: 'Luis R.', last: 'Hace 8 min', status: 'Activo', tone: 'green' },
    { initials: 'CR', name: 'Carlos Ramírez', email: 'carlos.ramirez@correo.mx', company: 'Studio Norte', origin: 'Instagram', owner: 'Andrea M.', last: 'Hoy 09:22', status: 'Activo', tone: 'green' },
    { initials: 'AT', name: 'Ana Torres', email: 'ana.torres@correo.mx', company: 'AT Consulting', origin: 'Facebook', owner: 'Luis R.', last: 'Ayer 17:15', status: 'Seguimiento', tone: 'amber' },
    { initials: 'MR', name: 'Miguel Ruiz', email: 'miguel.ruiz@correo.mx', company: 'MR Logística', origin: 'Instagram', owner: 'Andrea M.', last: '27 sept', status: 'Activo', tone: 'green' },
    { initials: 'SH', name: 'Sofía Hernández', email: 'sofia.hernandez@correo.mx', company: 'Casa H.', origin: 'Facebook', owner: 'Luis R.', last: '24 sept', status: 'Seguimiento', tone: 'amber' },
] as const;

const stats = [
    ['Clientes activos', '86', 'bg-[#23c6c8]'],
    ['Con seguimiento hoy', '12', 'bg-emerald-500'],
    ['Sin actividad 30 días', '7', 'bg-amber-500'],
    ['Nuevos este mes', '18', 'bg-violet-500'],
] as const;
</script>

<template>
    <Head title="Clientes" />

    <NexusPageHeader eyebrow="CRM / Clientes" title="Clientes" description="Consulta la cartera de clientes y su historial comercial.">
        <template #action>
            <button class="h-9 rounded-lg bg-[#139b9e] px-4 text-[11px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">+ Nuevo cliente</button>
        </template>
    </NexusPageHeader>

    <section class="grid overflow-hidden rounded-[10px] border border-slate-200 bg-white sm:grid-cols-2 xl:grid-cols-4">
        <div v-for="stat in stats" :key="stat[0]" class="relative border-b border-slate-100 p-4 last:border-0 sm:border-r xl:border-b-0">
            <span class="absolute left-0 top-0 h-full w-0.5" :class="stat[2]" />
            <p class="text-[10px] font-medium text-slate-500">{{ stat[0] }}</p>
            <p class="mt-1 text-xl font-semibold text-slate-900">{{ stat[1] }}</p>
        </div>
    </section>

    <section class="mt-5 rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="grid gap-3 md:grid-cols-[1.6fr_1fr_1fr_1fr]">
            <input class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[11px] outline-none focus:border-[#23c6c8] focus:bg-white" placeholder="Buscar por cliente, correo o empresa..." />
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none"><option>Estado: Todos</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none"><option>Responsable: Todos</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none"><option>Origen: Todos</option></select>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Cartera de clientes</h2></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr><th class="px-5 py-3">Cliente</th><th class="px-4 py-3">Empresa</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Responsable</th><th class="px-4 py-3">Última interacción</th><th class="px-4 py-3">Estado</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="client in clients" :key="client.email" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5"><div class="flex items-center gap-3"><div class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-[9px] font-semibold text-[#139b9e]">{{ client.initials }}</div><div><p class="text-[11px] font-semibold text-slate-900">{{ client.name }}</p><p class="mt-0.5 text-[9px] text-slate-500">{{ client.email }}</p></div></div></td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ client.company }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ client.origin }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ client.owner }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-500">{{ client.last }}</td>
                        <td class="px-4 py-3.5"><NexusBadge :label="client.status" :tone="client.tone" /></td>
                        <td class="px-5 py-3.5 text-right"><Link href="/crm/clientes/1048" class="text-[10px] font-semibold text-[#139b9e] hover:text-[#0e7f82]">Ver cliente</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
