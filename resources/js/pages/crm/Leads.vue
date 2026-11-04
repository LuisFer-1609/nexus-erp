<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Leads" />
    <NexusPageHeader
        eyebrow="CRM / Leads"
        title="Leads"
        description="Gestiona, prioriza y da seguimiento a las oportunidades comerciales."
    />
    <NexusEmptyState description="Aún no hay leads registrados." />
</template>
