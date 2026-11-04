<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Pre-nómina" />
    <NexusPageHeader
        eyebrow="Recursos Humanos / Pre-nómina"
        title="Pre-nómina"
        description="Calcula percepciones y deducciones a partir de asistencias registradas."
    />
    <NexusEmptyState
        description="Aún no hay periodos de pre-nómina disponibles."
    />
</template>
