<script setup lang="ts">
import { computed } from 'vue';
import NexusCatalogCrud, {
    type CatalogColumn,
    type CatalogField,
} from '@/components/nexus/NexusCatalogCrud.vue';

const props = defineProps<{
    items: Array<{
        id: number;
        code: string;
        name: string;
        area_id: number | null;
        area_name: string;
    }>;
    areas: Array<{ id: number; code: string; name: string }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'code', label: 'Clave', kind: 'code' },
    { key: 'name', label: 'Nombre del puesto' },
    { key: 'area_name', label: 'Área asociada' },
];

const fields = computed<CatalogField[]>(() => [
    {
        key: 'code',
        label: 'Clave',
        type: 'text',
        required: true,
        maxLength: 50,
        uppercase: true,
        placeholder: 'Ej. GER-RH, EJE-VTA',
    },
    {
        key: 'name',
        label: 'Nombre del puesto',
        type: 'text',
        required: true,
        maxLength: 150,
        placeholder: 'Ej. Ejecutivo de ventas',
    },
    {
        key: 'area_id',
        label: 'Área',
        type: 'select',
        defaultValue: '',
        options: [
            { value: '', label: 'Sin área específica' },
            ...props.areas.map((a) => ({
                value: a.id,
                label: `${a.code} — ${a.name}`,
            })),
        ],
    },
]);
</script>

<template>
    <NexusCatalogCrud
        slug="puestos"
        title="Puestos"
        singular="Puesto"
        description="Define los puestos laborales de la empresa y su relación con las áreas."
        empty-description="No hay puestos registrados con ese criterio."
        search-placeholder="Buscar por clave, puesto o área..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['code', 'name', 'area_name']"
    />
</template>
