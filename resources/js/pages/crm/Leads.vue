<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type Lead = {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    stage: string;
    source: string;
    meta_page_id: string | null;
    meta_form_id: string | null;
    meta_campaign_id: string | null;
    created_at: string;
};

defineProps<{
    leads: {
        data: Lead[];
        links: { url: string | null; label: string; active: boolean }[];
    };
}>();
</script>

<template>
    <Head title="Leads" />
    <NexusPageHeader
        eyebrow="CRM / Leads"
        title="Leads"
        description="Gestiona, prioriza y da seguimiento a las oportunidades comerciales."
    />
    <NexusEmptyState
        v-if="leads.data.length === 0"
        description="Aún no hay leads registrados."
    />
    <section
        v-else
        class="overflow-x-auto rounded-xl border border-slate-200 bg-white"
    >
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-500">
                <tr>
                    <th class="px-4 py-3">Lead</th>
                    <th>Contacto</th>
                    <th>Etapa</th>
                    <th>Origen</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="lead in leads.data"
                    :key="lead.id"
                    class="border-b border-slate-100"
                >
                    <td class="px-4 py-3 font-medium">{{ lead.full_name }}</td>
                    <td class="py-3">
                        {{ lead.email || lead.phone || 'Sin contacto' }}
                    </td>
                    <td class="py-3">{{ lead.stage }}</td>
                    <td class="py-3">
                        <span>{{ lead.source }}</span
                        ><span
                            v-if="lead.meta_page_id"
                            class="block text-xs text-slate-500"
                            >Página {{ lead.meta_page_id }}</span
                        ><span
                            v-if="lead.meta_form_id"
                            class="block text-xs text-slate-500"
                            >Formulario {{ lead.meta_form_id }}</span
                        ><span
                            v-if="lead.meta_campaign_id"
                            class="block text-xs text-slate-500"
                            >Campaña {{ lead.meta_campaign_id }}</span
                        >
                    </td>
                    <td class="py-3">
                        {{
                            new Date(lead.created_at).toLocaleDateString(
                                'es-MX',
                            )
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
        <nav v-if="leads.links.length > 3" class="flex flex-wrap gap-2 p-4">
            <a
                v-for="link in leads.links"
                :key="link.label"
                :href="link.url || '#'"
                :aria-disabled="!link.url"
                class="rounded border border-slate-200 px-3 py-1 text-sm"
                :class="{
                    'bg-slate-900 text-white': link.active,
                    'pointer-events-none opacity-40': !link.url,
                }"
                v-html="link.label"
            />
        </nav>
    </section>
</template>
