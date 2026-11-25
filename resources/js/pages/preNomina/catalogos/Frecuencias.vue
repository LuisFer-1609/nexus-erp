<script setup lang="ts">
import NexusCatalogCrud, {
    type CatalogColumn,
    type CatalogField,
} from '@/components/nexus/NexusCatalogCrud.vue';

defineProps<{
    items: Array<{ id: number; code: string; name: string; days: number }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'code', label: 'Clave', kind: 'code' },
    { key: 'name', label: 'Nombre' },
    {
        key: 'days',
        label: 'Días por período',
        kind: 'badge',
        toneResolver: () => 'cyan',
        valueFormatter: (row) => `${row.days} días`,
    },
];

const fields: CatalogField[] = [
    {
        key: 'code',
        label: 'Clave',
        type: 'text',
        required: true,
        maxLength: 50,
        placeholder: 'Ej. semanal, quincenal, mensual',
    },
    {
        key: 'name',
        label: 'Nombre',
        type: 'text',
        required: true,
        maxLength: 100,
        placeholder: 'Ej. Quincenal',
    },
    {
        key: 'days',
        label: 'Días',
        type: 'number',
        required: true,
        min: 1,
        max: 365,
        defaultValue: 15,
        hint: 'Valores habituales: 7 (Semanal), 15 (Quincenal) o 30 (Mensual).',
    },
];
</script>

<template>
    <NexusCatalogCrud
        slug="frecuencias"
        title="Frecuencias de nómina"
        singular="Frecuencia"
        description="Configura los ciclos de pago disponibles para empleados y períodos de pre-nómina."
        empty-description="No hay frecuencias de nómina registradas."
        search-placeholder="Buscar por clave o nombre de frecuencia..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['code', 'name']"
    />
</template>
