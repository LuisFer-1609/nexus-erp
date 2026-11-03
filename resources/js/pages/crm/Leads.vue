<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const leads = [
    { initials: 'ML', name: 'María López', email: 'maria.lopez@correo.mx', origin: 'Facebook', stage: 'Contactado', stageTone: 'green', priority: 'Alta', priorityTone: 'red', owner: 'Luis R.', interaction: 'Hace 8 min', next: 'Hoy 16:00' },
    { initials: 'CR', name: 'Carlos Ramírez', email: 'carlos.ramirez@correo.mx', origin: 'Instagram', stage: 'Nuevo', stageTone: 'blue', priority: 'Media', priorityTone: 'amber', owner: 'Andrea M.', interaction: 'Hace 24 min', next: 'Mañana' },
    { initials: 'AT', name: 'Ana Torres', email: 'ana.torres@correo.mx', origin: 'Facebook', stage: 'Negociación', stageTone: 'amber', priority: 'Alta', priorityTone: 'red', owner: 'Luis R.', interaction: 'Hoy 10:42', next: 'Hoy 17:30' },
    { initials: 'MR', name: 'Miguel Ruiz', email: 'miguel.ruiz@correo.mx', origin: 'Instagram', stage: 'Cerrado', stageTone: 'cyan', priority: 'Media', priorityTone: 'amber', owner: 'Andrea M.', interaction: 'Ayer 16:20', next: '—' },
    { initials: 'SH', name: 'Sofía Hernández', email: 'sofia.hernandez@correo.mx', origin: 'Facebook', stage: 'Contactado', stageTone: 'green', priority: 'Baja', priorityTone: 'slate', owner: 'Luis R.', interaction: 'Ayer 11:06', next: '30 sept' },
    { initials: 'DC', name: 'Diego Castro', email: 'diego.castro@correo.mx', origin: 'Facebook', stage: 'Nuevo', stageTone: 'blue', priority: 'Media', priorityTone: 'amber', owner: 'Luis R.', interaction: '28 sept', next: 'Hoy 18:00' },
] as const;
</script>

<template>
    <Head title="Leads" />

    <NexusPageHeader
        eyebrow="CRM / Leads"
        title="Leads"
        description="Gestiona, prioriza y da seguimiento a las oportunidades comerciales."
    >
        <template #action>
            <button class="h-9 rounded-lg bg-[#139b9e] px-4 text-[11px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none">
                + Nuevo lead
            </button>
        </template>
    </NexusPageHeader>

    <div class="mb-4 flex w-fit rounded-lg border border-slate-200 bg-white p-1 text-[11px]">
        <button class="rounded-md bg-[#0a161e] px-4 py-1.5 font-semibold text-white">Lista</button>
        <button class="rounded-md px-4 py-1.5 font-medium text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">Embudo</button>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none focus:border-[#23c6c8]"><option>Etapa: Todas</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none focus:border-[#23c6c8]"><option>Origen: Todos</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none focus:border-[#23c6c8]"><option>Responsable: Todos</option></select>
            <select class="h-9 rounded-lg border border-slate-200 px-3 text-[11px] text-slate-600 outline-none focus:border-[#23c6c8]"><option>Prioridad: Todas</option></select>
            <input class="h-9 rounded-lg border border-slate-200 bg-slate-50 px-3 text-[11px] outline-none transition focus:border-[#23c6c8] focus:bg-white" placeholder="Buscar nombre, correo o campaña..." />
        </div>
    </section>

    <section class="mt-5 overflow-hidden rounded-[10px] border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 py-4">
            <h2 class="text-sm font-semibold text-slate-900">Todos los leads</h2>
            <p class="mt-0.5 text-[10px] text-slate-500">128 registros · ordenados por actividad reciente</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1050px] text-left">
                <thead class="bg-slate-50 text-[10px] font-semibold uppercase tracking-[0.05em] text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Lead</th><th class="px-4 py-3">Origen</th><th class="px-4 py-3">Etapa</th>
                        <th class="px-4 py-3">Prioridad</th><th class="px-4 py-3">Responsable</th><th class="px-4 py-3">Última interacción</th>
                        <th class="px-4 py-3">Próxima acción</th><th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="lead in leads" :key="lead.email" class="transition-colors hover:bg-slate-50/80">
                        <td class="px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <div class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-[9px] font-semibold text-[#139b9e]">{{ lead.initials }}</div>
                                <div><p class="text-[11px] font-semibold text-slate-900">{{ lead.name }}</p><p class="mt-0.5 text-[9px] text-slate-500">{{ lead.email }}</p></div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ lead.origin }}</td>
                        <td class="px-4 py-3.5"><NexusBadge :label="lead.stage" :tone="lead.stageTone" /></td>
                        <td class="px-4 py-3.5"><NexusBadge :label="lead.priority" :tone="lead.priorityTone" /></td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-700">{{ lead.owner }}</td>
                        <td class="px-4 py-3.5 text-[10px] text-slate-500">{{ lead.interaction }}</td>
                        <td class="px-4 py-3.5 text-[10px] font-medium text-slate-700">{{ lead.next }}</td>
                        <td class="px-5 py-3.5 text-right"><Link href="/crm/clientes/1048" class="text-[10px] font-semibold text-[#139b9e] hover:text-[#0e7f82]">Ver ficha</Link></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
