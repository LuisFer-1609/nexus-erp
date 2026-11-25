<script setup lang="ts">
import NexusCatalogCrud, {
    type CatalogColumn,
    type CatalogField,
} from '@/components/nexus/NexusCatalogCrud.vue';

defineProps<{
    items: Array<{
        id: number;
        nationality: string;
        c_pais: string | null;
        country: string | null;
    }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'c_pais', label: 'Clave país', kind: 'code' },
    { key: 'nationality', label: 'Gentilicio' },
    { key: 'country', label: 'País' },
];

const fields: CatalogField[] = [
    {
        key: 'nationality',
        label: 'Gentilicio',
        type: 'text',
        required: true,
        maxLength: 100,
        placeholder: 'Ej. Mexicana',
    },
    {
        key: 'c_pais',
        label: 'Clave del país (ISO)',
        type: 'text',
        maxLength: 10,
        uppercase: true,
        placeholder: 'Ej. MEX',
    },
    {
        key: 'country',
        label: 'País',
        type: 'text',
        maxLength: 100,
        placeholder: 'Ej. México',
    },
];
</script>

<template>
    <NexusCatalogCrud
        slug="nacionalidades"
        title="Nacionalidades"
        singular="Nacionalidad"
        description="Catálogo global de nacionalidades y claves de país disponibles en el alta de empleados."
        empty-description="No hay nacionalidades que coincidan con la búsqueda."
        search-placeholder="Buscar por gentilicio, clave o país..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['nationality', 'c_pais', 'country']"
    />
</template>
