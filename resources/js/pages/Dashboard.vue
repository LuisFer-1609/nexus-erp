<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Dashboard CRM" />
    <NexusPageHeader
        eyebrow="Dashboard / CRM"
        title="Dashboard"
        description="Resumen general de la actividad comercial."
    />
    <NexusEmptyState
        description="Aún no hay información comercial para mostrar."
    />
</template>
