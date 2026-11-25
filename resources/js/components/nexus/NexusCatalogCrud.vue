<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import {
    destroy as destroyCatalog,
    store as storeCatalog,
    update as updateCatalog,
} from '@/routes/pre-nomina/catalogos';

export type CatalogOption = {
    value: string | number;
    label: string;
};

export type CatalogField = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'checkbox' | 'textarea';
    required?: boolean;
    placeholder?: string;
    hint?: string;
    options?: CatalogOption[];
    defaultValue?: string | number | boolean | null;
    maxLength?: number;
    min?: number;
    max?: number;
    uppercase?: boolean;
};

export type CatalogColumn = {
    key: string;
    label: string;
    kind?: 'text' | 'code' | 'badge' | 'boolean';
    toneResolver?: (
        row: Record<string, unknown>,
    ) => 'cyan' | 'green' | 'amber' | 'red' | 'slate' | 'blue';
    valueFormatter?: (row: Record<string, unknown>) => string;
};

const props = defineProps<{
    slug: string;
    title: string;
    singular: string;
    description: string;
    emptyDescription: string;
    searchPlaceholder: string;
    items: Array<Record<string, unknown>>;
    columns: CatalogColumn[];
    fields: CatalogField[];
    searchKeys: string[];
}>();

const searchQuery = ref('');
const modalOpen = ref(false);
const editingId = ref<number | null>(null);
const deletingItem = ref<Record<string, unknown> | null>(null);
const deleteBusy = ref(false);

type CatalogFormValue = string | number | boolean | null;

const buildInitialValues = (
    row?: Record<string, unknown>,
): Record<string, CatalogFormValue> => {
    const values: Record<string, CatalogFormValue> = {};
    for (const field of props.fields) {
        if (row && row[field.key] !== undefined) {
            const cell = row[field.key];
            values[field.key] =
                typeof cell === 'string' ||
                typeof cell === 'number' ||
                typeof cell === 'boolean' ||
                cell === null
                    ? cell
                    : '';
        } else if (field.defaultValue !== undefined) {
            values[field.key] = field.defaultValue;
        } else if (field.type === 'checkbox') {
            values[field.key] = true;
        } else {
            values[field.key] = '';
        }
    }

    return values;
};

const form = useForm<Record<string, CatalogFormValue>>(buildInitialValues());

const filteredItems = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) {
        return props.items;
    }

    return props.items.filter((item) =>
        props.searchKeys.some((key) =>
            String(item[key] ?? '')
                .toLowerCase()
                .includes(q),
        ),
    );
});

const openCreate = (): void => {
    editingId.value = null;
    form.clearErrors();
    Object.assign(form, buildInitialValues());
    modalOpen.value = true;
};

const openEdit = (row: Record<string, unknown>): void => {
    editingId.value = Number(row.id);
    form.clearErrors();
    Object.assign(form, buildInitialValues(row));
    modalOpen.value = true;
};

const closeModal = (): void => {
    modalOpen.value = false;
    editingId.value = null;
    form.clearErrors();
};

const submitForm = (): void => {
    const payload = buildInitialValues();
    for (const field of props.fields) {
        const raw = form[field.key];
        if (field.type === 'select' && (raw === '' || raw === null)) {
            payload[field.key] = null;
        } else if (field.type === 'number') {
            payload[field.key] = raw === '' ? null : Number(raw);
        } else if (field.type === 'checkbox') {
            payload[field.key] = Boolean(raw);
        } else {
            payload[field.key] = raw ?? '';
        }
    }

    form.transform(() => payload);

    if (editingId.value === null) {
        form.post(storeCatalog.url(props.slug), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.put(
            updateCatalog.url({ catalog: props.slug, id: editingId.value }),
            {
                preserveScroll: true,
                onSuccess: () => closeModal(),
            },
        );
    }
};

const confirmDelete = (): void => {
    if (!deletingItem.value) {
        return;
    }

    deleteBusy.value = true;
    router.delete(
        destroyCatalog.url({
            catalog: props.slug,
            id: Number(deletingItem.value.id),
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                deletingItem.value = null;
            },
            onError: (errors) => {
                const msg =
                    errors.catalog ??
                    Object.values(errors)[0] ??
                    'No se pudo eliminar el registro.';
                toast.error(String(msg));
                deletingItem.value = null;
            },
            onFinish: () => {
                deleteBusy.value = false;
            },
        },
    );
};

const displayCell = (
    row: Record<string, unknown>,
    col: CatalogColumn,
): string => {
    if (col.valueFormatter) {
        return col.valueFormatter(row);
    }
    const value = row[col.key];
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
};
</script>

<template>
    <div class="space-y-5">
        <Head :title="title" />

        <NexusPageHeader
            eyebrow="PRE-NÓMINA / Catálogos"
            :title="title"
            :description="description"
        >
            <template #action>
                <button
                    type="button"
                    class="inline-flex h-11 items-center gap-2 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white shadow-sm transition hover:bg-[#1bb2b4] focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                    @click="openCreate"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    Nuevo {{ singular.toLowerCase() }}
                </button>
            </template>
        </NexusPageHeader>

        <section class="rounded-[12px] border border-slate-200/90 bg-white p-4">
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="relative flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                        aria-hidden="true"
                    />
                    <input
                        v-model="searchQuery"
                        type="search"
                        class="h-10 w-full rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-xs text-slate-800 transition outline-none focus:border-[#23c6c8] focus:bg-white"
                        :placeholder="searchPlaceholder"
                    />
                </div>
                <p
                    class="shrink-0 text-xs font-medium text-slate-500 tabular-nums"
                >
                    {{ filteredItems.length }} de {{ items.length }} registros
                </p>
            </div>
        </section>

        <NexusEmptyState
            v-if="filteredItems.length === 0"
            :description="emptyDescription"
        />

        <section
            v-else
            class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
        >
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-left">
                    <thead
                        class="bg-slate-50 text-xs font-semibold tracking-[0.05em] text-slate-500 uppercase"
                    >
                        <tr>
                            <th
                                v-for="col in columns"
                                :key="col.key"
                                class="px-5 py-3"
                            >
                                {{ col.label }}
                            </th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="row in filteredItems"
                            :key="String(row.id)"
                            class="transition-colors hover:bg-slate-50/80"
                        >
                            <td
                                v-for="col in columns"
                                :key="col.key"
                                class="px-5 py-3.5 text-xs"
                            >
                                <span
                                    v-if="col.kind === 'code'"
                                    class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700"
                                >
                                    {{ displayCell(row, col) }}
                                </span>
                                <NexusBadge
                                    v-else-if="
                                        col.kind === 'badge' ||
                                        col.kind === 'boolean'
                                    "
                                    :label="displayCell(row, col)"
                                    :tone="
                                        col.toneResolver
                                            ? col.toneResolver(row)
                                            : row[col.key]
                                              ? 'green'
                                              : 'slate'
                                    "
                                />
                                <span v-else class="font-medium text-slate-800">
                                    {{ displayCell(row, col) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                                        :aria-label="`Editar ${singular.toLowerCase()}`"
                                        @click="openEdit(row)"
                                    >
                                        <Pencil
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex h-8 items-center gap-1 rounded-md px-2.5 text-xs font-medium text-red-600 transition hover:bg-red-50 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                                        :aria-label="`Eliminar ${singular.toLowerCase()}`"
                                        @click="deletingItem = row"
                                    >
                                        <Trash2
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        Eliminar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Modal Alta / Edición -->
        <Teleport to="body">
            <div
                v-if="modalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-[2px]"
                @click.self="closeModal"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-6 shadow-2xl"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p
                                class="text-[11px] font-semibold tracking-wide text-[#139b9e] uppercase"
                            >
                                {{
                                    editingId === null
                                        ? `Nuevo ${singular.toLowerCase()}`
                                        : `Editar ${singular.toLowerCase()}`
                                }}
                            </p>
                            <h2
                                class="mt-1 text-lg font-semibold text-slate-900"
                            >
                                {{ title }}
                            </h2>
                        </div>
                        <button
                            type="button"
                            class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Cerrar"
                            @click="closeModal"
                        >
                            <X class="size-4" />
                        </button>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="submitForm">
                        <div v-for="field in fields" :key="field.key">
                            <label
                                v-if="field.type === 'checkbox'"
                                class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700"
                            >
                                <input
                                    v-model="form[field.key]"
                                    type="checkbox"
                                    class="size-4 accent-[#23c6c8]"
                                />
                                {{ field.label }}
                            </label>

                            <template v-else>
                                <label
                                    class="block text-xs font-semibold text-slate-700"
                                >
                                    {{ field.label }}
                                    <span
                                        v-if="field.required"
                                        class="text-red-600"
                                        >*</span
                                    >
                                    <select
                                        v-if="field.type === 'select'"
                                        v-model="form[field.key]"
                                        class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-800 outline-none focus:border-[#23c6c8]"
                                    >
                                        <option
                                            v-for="opt in field.options ?? []"
                                            :key="String(opt.value)"
                                            :value="opt.value"
                                        >
                                            {{ opt.label }}
                                        </option>
                                    </select>
                                    <textarea
                                        v-else-if="field.type === 'textarea'"
                                        v-model="form[field.key] as string"
                                        rows="3"
                                        :maxlength="field.maxLength"
                                        :placeholder="field.placeholder"
                                        class="mt-1.5 block w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-normal text-slate-800 outline-none focus:border-[#23c6c8]"
                                    />
                                    <input
                                        v-else-if="field.type === 'number'"
                                        v-model="
                                            form[field.key] as number | string
                                        "
                                        type="number"
                                        :min="field.min"
                                        :max="field.max"
                                        :required="field.required"
                                        :placeholder="field.placeholder"
                                        class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-800 outline-none focus:border-[#23c6c8]"
                                    />
                                    <input
                                        v-else
                                        v-model="form[field.key] as string"
                                        type="text"
                                        :maxlength="field.maxLength"
                                        :required="field.required"
                                        :placeholder="field.placeholder"
                                        :class="
                                            field.uppercase ? 'uppercase' : ''
                                        "
                                        class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal text-slate-800 outline-none focus:border-[#23c6c8]"
                                    />
                                </label>
                                <p
                                    v-if="field.hint"
                                    class="mt-1 text-[11px] text-slate-500"
                                >
                                    {{ field.hint }}
                                </p>
                            </template>

                            <InputError
                                class="mt-1"
                                :message="form.errors[field.key]"
                            />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                class="h-10 rounded-lg px-4 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                @click="closeModal"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="h-10 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white transition hover:bg-[#1bb2b4] disabled:opacity-50"
                            >
                                {{
                                    form.processing
                                        ? 'Guardando...'
                                        : 'Guardar cambios'
                                }}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- Modal Confirmar Eliminación -->
            <div
                v-if="deletingItem"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 backdrop-blur-[2px]"
                @click.self="deletingItem = null"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    class="w-full max-w-sm rounded-xl border border-slate-200 bg-white p-6 shadow-2xl"
                >
                    <h2 class="text-base font-semibold text-slate-900">
                        Eliminar {{ singular.toLowerCase() }}
                    </h2>
                    <p class="mt-2 text-xs leading-relaxed text-slate-600">
                        ¿Seguro que deseas eliminar este registro? Si está en
                        uso en empleados o nóminas, el sistema protegerá la
                        relación.
                    </p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            type="button"
                            class="h-10 rounded-lg px-4 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            @click="deletingItem = null"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="deleteBusy"
                            class="h-10 rounded-lg bg-red-600 px-4 text-xs font-semibold text-white hover:bg-red-700 disabled:opacity-50"
                            @click="confirmDelete"
                        >
                            {{ deleteBusy ? 'Eliminando...' : 'Sí, eliminar' }}
                        </button>
                    </div>
                </section>
            </div>
        </Teleport>
    </div>
</template>
