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
        payroll_concept_type_id: number;
        type_name: string;
        type_code: string;
        is_active: boolean;
    }>;
    conceptTypes: Array<{ id: number; code: string; name: string }>;
}>();

const columns: CatalogColumn[] = [
    { key: 'code', label: 'Clave', kind: 'code' },
    { key: 'name', label: 'Concepto' },
    {
        key: 'type_name',
        label: 'Tipo',
        kind: 'badge',
        toneResolver: (row) =>
            row.type_code === 'percepcion' ? 'green' : 'amber',
    },
    {
        key: 'is_active',
        label: 'Estado',
        kind: 'boolean',
        toneResolver: (row) => (row.is_active ? 'cyan' : 'slate'),
        valueFormatter: (row) => (row.is_active ? 'Activo' : 'Inactivo'),
    },
];

const fields = computed<CatalogField[]>(() => [
    {
        key: 'code',
        label: 'Clave',
        type: 'text',
        required: true,
        maxLength: 50,
        uppercase: true,
        placeholder: 'Ej. SUE, BON, ISR, IMSS',
    },
    {
        key: 'name',
        label: 'Nombre del concepto',
        type: 'text',
        required: true,
        maxLength: 150,
        placeholder: 'Ej. Bono de productividad',
    },
    {
        key: 'payroll_concept_type_id',
        label: 'Tipo de concepto',
        type: 'select',
        required: true,
        defaultValue: props.conceptTypes[0]?.id ?? 1,
        options: props.conceptTypes.map((t) => ({
            value: t.id,
            label: t.name,
        })),
    },
    {
        key: 'is_active',
        label: 'Concepto activo',
        type: 'checkbox',
        defaultValue: true,
    },
]);
</script>

<template>
    <NexusCatalogCrud
        slug="conceptos"
        title="Conceptos de nómina"
        singular="Concepto"
        description="Administra las percepciones (Sueldo, Bono) y deducciones (ISR, IMSS) aplicables en pre-nómina."
        empty-description="No hay conceptos de nómina registrados."
        search-placeholder="Buscar por clave, concepto o tipo..."
        :items="items"
        :columns="columns"
        :fields="fields"
        :search-keys="['code', 'name', 'type_name']"
    />
</template>
