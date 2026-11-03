<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Contact, Handshake, Target, UserRoundPlus } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const leads = [
    { initials: 'ML', name: 'María López', email: 'maria.lopez@correo.mx', origin: 'Facebook', stage: 'Nuevo', tone: 'blue', owner: 'Luis R.', activity: 'Hace 8 min' },
    { initials: 'CR', name: 'Carlos Ramírez', email: 'carlos.ramirez@correo.mx', origin: 'Instagram', stage: 'Contactado', tone: 'green', owner: 'Andrea M.', activity: 'Hace 24 min' },
    { initials: 'AT', name: 'Ana Torres', email: 'ana.torres@correo.mx', origin: 'Facebook', stage: 'Negociación', tone: 'amber', owner: 'Luis R.', activity: 'Hoy 10:42' },
    { initials: 'MR', name: 'Miguel Ruiz', email: 'miguel.ruiz@correo.mx', origin: 'Instagram', stage: 'Cerrado', tone: 'cyan', owner: 'Andrea M.', activity: 'Ayer 16:20' },
    { initials: 'SH', name: 'Sofía Hernández', email: 'sofia.hernandez@correo.mx', origin: 'Facebook', stage: 'Contactado', tone: 'green', owner: 'Luis R.', activity: 'Ayer 11:06' },
] as const;
</script>

<template>
    <Head title="Dashboard CRM" />

    <NexusPageHeader
        eyebrow="Dashboard / CRM"
        title="Buenos días, Luis"
        description="Aquí tienes el pulso comercial de Acme Marketing."
    />

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <NexusKpiCard label="Leads totales" value="128" helper="+12% este mes" :icon="Contact" />
        <NexusKpiCard label="Nuevos hoy" value="14" helper="+4 vs ayer" :icon="UserRoundPlus" tone="green" />
        <NexusKpiCard label="En negociación" value="21" helper="16% del pipeline" :icon="Handshake" tone="amber" />
        <NexusKpiCard label="Cerrados" value="37" helper="+8% este mes" :icon="Target" tone="violet" />
    </section>

    <section class="mt-5 rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h2 class="text-xs font-semibold text-slate-800">Filtrar leads</h2>
            <Link
                href="/crm/leads"
                class="text-[11px] font-semibold text-[#139b9e] transition hover:text-[#0e7f82]"
            >
                Ver todos
            </Link>
        </div>
        <div class="grid gap-3 md:grid-cols-4 xl:grid-cols-[1fr_1fr_1fr_1.4fr_auto]">
            <select class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-slate-600 outline-none transition focus:border-[#23c6c8]">
                <option>Etapa: Todas</option>
            </select>
            <select class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-slate-600 outline-none transition focus:border-[#23c6c8]">
                <option>Origen: Todos</option>
            </select>
            <select class="h-9 rounded-lg border border-slate-200 bg-white px-3 text-[11px] text-slate-600 outline-none transition focus:border-[#23c6c8]">
                <option>Responsable: Todos</option>
            </select>
            <input class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[11px] outline-none transition focus:border-[#23c6c8] focus:bg-white" placeholder="Buscar nombre o correo..." />
            <button class="h-9 rounded-lg bg-[#139b9e] px-4 text-[11px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">
                + Nuevo lead
            </button>
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
            <div>
                <h2 class="text-sm font-semibold text-slate-900">Leads recientes</h2>
                <p class="mt-0.5 text-[10px] text-slate-500">Última actualización hace 2 min</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Lead</th>
                        <th class="px-4 py-3">Origen</th>
                        <th class="px-4 py-3">Etapa</th>
                        <th class="px-4 py-3">Responsable</th>
                        <th class="px-4 py-3">Última actividad</th>
                        <th class="px-5 py-3 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="lead in leads" :key="lead.email" class="group transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-[9px] font-semibold text-[#139b9e]">{{ lead.initials }}</div>
                                <div>
                                    <p class="text-[11px] font-semibold text-slate-900">{{ lead.name }}</p>
                                    <p class="mt-0.5 text-[9px] text-slate-500">{{ lead.email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ lead.origin }}</td>
                        <td class="px-4 py-3.5"><NexusBadge :label="lead.stage" :tone="lead.tone" /></td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ lead.owner }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-500">{{ lead.activity }}</td>
                        <td class="px-5 py-3.5 text-right">
                            <Link href="/crm/clientes/1048" class="text-[10px] font-semibold text-[#139b9e] transition group-hover:text-[#0d7e80]">Ver ficha</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
