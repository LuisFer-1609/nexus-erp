<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Asistente IA" />
    <NexusPageHeader
        eyebrow="Asistente empresarial"
        title="Nexus Assistant"
        description="Consulta información empresarial mediante el asistente."
    />
    <NexusEmptyState description="No hay conversaciones registradas." />
</template>
