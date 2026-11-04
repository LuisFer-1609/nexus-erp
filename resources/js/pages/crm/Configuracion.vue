<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Configuración" />
    <NexusPageHeader
        eyebrow="CRM / Configuración"
        title="Configuración"
        description="Administra la empresa, usuarios, roles e integraciones del CRM."
    />
    <NexusEmptyState
        description="No hay configuraciones empresariales disponibles."
    />
</template>
