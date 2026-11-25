<script setup lang="ts">
import NexusCatalogCrud, {
    type CatalogColumn,
    type CatalogField,
} from '@/components/nexus/NexusCatalogCrud.vue';

defineProps<{
    items: Array<{ id: number; code: string; name: string }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'code', label: 'Clave', kind: 'code' },
    { key: 'name', label: 'Nombre del área' },
];

const fields: CatalogField[] = [
    {
        key: 'code',
        label: 'Clave',
        type: 'text',
        required: true,
        maxLength: 50,
        uppercase: true,
        placeholder: 'Ej. RH, VTA, OPS',
    },
    {
        key: 'name',
        label: 'Nombre del área',
        type: 'text',
        required: true,
        maxLength: 150,
        placeholder: 'Ej. Recursos Humanos',
    },
];
</script>

<template>
    <NexusCatalogCrud
        slug="areas"
        title="Áreas"
        singular="Área"
        description="Administra los departamentos o áreas operativas disponibles para puestos y empleados."
        empty-description="No hay áreas registradas con ese criterio."
        search-placeholder="Buscar por clave o nombre de área..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['code', 'name']"
    />
</template>
