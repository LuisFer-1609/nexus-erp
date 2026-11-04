<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Asistencias" />
    <NexusPageHeader
        eyebrow="Recursos Humanos / Asistencias"
        title="Control de asistencias"
        description="Registro diario de entradas, salidas, retardos, faltas y horas extra."
    />
    <NexusEmptyState description="Aún no hay registros de asistencia." />
</template>
