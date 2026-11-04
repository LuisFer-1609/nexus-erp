<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Clientes" />
    <NexusPageHeader
        eyebrow="CRM / Clientes"
        title="Clientes"
        description="Consulta la cartera de clientes y su historial comercial."
    />
    <NexusEmptyState description="Aún no hay clientes registrados." />
</template>
