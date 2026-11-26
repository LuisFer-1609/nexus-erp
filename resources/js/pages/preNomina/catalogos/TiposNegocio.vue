<script setup lang="ts">
import NexusCatalogCrud, {
    type CatalogColumn,
    type CatalogField,
} from '@/components/nexus/NexusCatalogCrud.vue';

defineProps<{
    items: Array<{
        id: number;
        code: string;
        name: string;
        descripcion: string | null;
    }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'code', label: 'Clave', kind: 'code' },
    { key: 'name', label: 'Nombre' },
    { key: 'descripcion', label: 'Descripción' },
];

const fields: CatalogField[] = [
    {
        key: 'code',
        label: 'Clave',
        type: 'text',
        required: true,
        maxLength: 50,
        uppercase: true,
        placeholder: 'Ej. COM, SRV, MAN, TEC',
    },
    {
        key: 'name',
        label: 'Nombre',
        type: 'text',
        required: true,
        maxLength: 100,
        placeholder: 'Ej. Tecnología',
    },
    {
        key: 'descripcion',
        label: 'Descripción',
        type: 'textarea',
        maxLength: 255,
        placeholder: 'Describe brevemente el giro comercial...',
    },
];
</script>

<template>
    <NexusCatalogCrud
        slug="tipos-negocio"
        title="Tipos de negocio"
        singular="Tipo de negocio"
        description="Administra los giros o tipos de negocio registrados en la plataforma."
        empty-description="No hay tipos de negocio registrados."
        search-placeholder="Buscar por clave, nombre o descripción..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['code', 'name', 'descripcion']"
    />
</template>
