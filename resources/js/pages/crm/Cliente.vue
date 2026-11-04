<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Ficha de cliente" />
    <NexusPageHeader
        eyebrow="CRM / Clientes"
        title="Ficha de cliente"
        description="Información y actividad del cliente seleccionado."
    />
    <NexusEmptyState description="No hay información de cliente disponible." />
</template>
