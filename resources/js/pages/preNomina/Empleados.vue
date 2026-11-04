<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
</script>

<template>
    <Head title="Empleados" />
    <NexusPageHeader
        eyebrow="PRE-NÓMINA / Empleados"
        title="Empleados"
        description="Gestiona el personal registrado y su información laboral."
    />
    <NexusEmptyState description="Aún no hay empleados registrados." />
</template>
