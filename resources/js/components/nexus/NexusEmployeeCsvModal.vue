<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    Upload,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import {
    csvConfirm,
    csvPreview,
    csvTemplate,
} from '@/routes/pre-nomina/empleados';

type CsvPayload = Record<string, string | number | null>;

type PreviewRow = {
    rowNumber: number;
    valid: boolean;
    errors: string[];
    raw: {
        code: string;
        first_name: string;
        last_name: string;
        curp: string;
        area: string;
        position: string;
        frequency: string;
        hire_date: string;
    };
    payload: CsvPayload | null;
};

type PreviewResponse = {
    summary: { total: number; valid: number; invalid: number };
    rows: PreviewRow[];
};

defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const selectedFileName = ref('');
const parsing = ref(false);
const importing = ref(false);
const uploadError = ref('');
const preview = ref<PreviewResponse | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const validPayloads = computed(() =>
    (preview.value?.rows ?? [])
        .filter((row) => row.valid && row.payload !== null)
        .map((row) => row.payload as CsvPayload),
);

const xsrf = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const resetState = (): void => {
    selectedFileName.value = '';
    uploadError.value = '';
    preview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const close = (): void => {
    resetState();
    emit('close');
};

const handleFileSelected = async (event: Event): Promise<void> => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        return;
    }

    selectedFileName.value = file.name;
    uploadError.value = '';
    preview.value = null;
    parsing.value = true;

    try {
        const body = new FormData();
        body.append('file', file);

        const response = await fetch(csvPreview.url(), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            credentials: 'same-origin',
            body,
        });

        const data = (await response.json().catch(() => ({}))) as
            | PreviewResponse
            | { message?: string; errors?: Record<string, string[]> };

        if (!response.ok) {
            const errBag = (data as { errors?: Record<string, string[]> })
                .errors;
            uploadError.value =
                errBag?.file?.[0] ??
                (data as { message?: string }).message ??
                'No se pudo leer el archivo CSV.';

            return;
        }

        preview.value = data as PreviewResponse;
    } catch {
        uploadError.value = 'Error de conexión al analizar el CSV.';
    } finally {
        parsing.value = false;
    }
};

const confirmImport = (): void => {
    if (validPayloads.value.length === 0) {
        return;
    }

    importing.value = true;
    router.post(
        csvConfirm.url(),
        { rows: validPayloads.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                close();
            },
            onError: () => {
                toast.error('No se pudo completar la importación.');
            },
            onFinish: () => {
                importing.value = false;
            },
        },
    );
};
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 px-4 py-6 backdrop-blur-[2px]"
            @click.self="close"
        >
            <section
                role="dialog"
                aria-modal="true"
                class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-slate-100 px-6 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-[#139b9e]"
                        >
                            <FileSpreadsheet class="size-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Importar empleados desde CSV
                            </h2>
                            <p class="text-xs text-slate-500">
                                Sube tu archivo, revisa errores en la vista
                                previa y confirma los registros válidos.
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Cerrar"
                        @click="close"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-5">
                    <div
                        class="flex flex-col gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <p class="text-xs font-semibold text-slate-800">
                                Formato NexusERP (.csv)
                            </p>
                            <p class="text-[11px] text-slate-500">
                                Puedes usar claves o nombres exactos de los
                                catálogos (áreas, puestos, nacionalidades y
                                frecuencias).
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a
                                :href="csvTemplate.url()"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                <Download class="size-3.5" />
                                Plantilla CSV
                            </a>
                            <label
                                class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-lg bg-[#0a161e] px-3 text-xs font-semibold text-white transition hover:bg-[#162732]"
                            >
                                <Upload class="size-3.5" />
                                {{
                                    parsing
                                        ? 'Analizando...'
                                        : selectedFileName || 'Elegir archivo'
                                }}
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept=".csv,.txt,text/csv"
                                    class="sr-only"
                                    @change="handleFileSelected"
                                />
                            </label>
                        </div>
                    </div>

                    <div
                        v-if="uploadError"
                        class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-xs text-red-700"
                    >
                        <AlertCircle class="mt-0.5 size-4 shrink-0" />
                        <span>{{ uploadError }}</span>
                    </div>

                    <template v-if="preview">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div
                                class="rounded-lg border border-slate-200 bg-white px-4 py-3"
                            >
                                <p
                                    class="text-[11px] font-medium text-slate-500"
                                >
                                    Filas detectadas
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-900 tabular-nums"
                                >
                                    {{ preview.summary.total }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-emerald-200 bg-emerald-50/50 px-4 py-3"
                            >
                                <p
                                    class="text-[11px] font-medium text-emerald-700"
                                >
                                    Listos para importar
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-emerald-800 tabular-nums"
                                >
                                    {{ preview.summary.valid }}
                                </p>
                            </div>
                            <div
                                class="rounded-lg border border-red-200 bg-red-50/50 px-4 py-3"
                            >
                                <p class="text-[11px] font-medium text-red-700">
                                    Con errores (se omitirán)
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-red-800 tabular-nums"
                                >
                                    {{ preview.summary.invalid }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="overflow-hidden rounded-xl border border-slate-200"
                        >
                            <div class="overflow-x-auto">
                                <table class="w-full min-w-[760px] text-left">
                                    <thead
                                        class="bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        <tr>
                                            <th class="px-3 py-2.5">Fila</th>
                                            <th class="px-3 py-2.5">Estado</th>
                                            <th class="px-3 py-2.5">Clave</th>
                                            <th class="px-3 py-2.5">
                                                Nombre completo
                                            </th>
                                            <th class="px-3 py-2.5">
                                                Área / Puesto
                                            </th>
                                            <th class="px-3 py-2.5">
                                                Observaciones
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="row in preview.rows"
                                            :key="row.rowNumber"
                                            :class="
                                                row.valid
                                                    ? 'bg-white'
                                                    : 'bg-red-50/40'
                                            "
                                        >
                                            <td
                                                class="px-3 py-2.5 text-xs font-semibold text-slate-500 tabular-nums"
                                            >
                                                #{{ row.rowNumber }}
                                            </td>
                                            <td class="px-3 py-2.5">
                                                <NexusBadge
                                                    :label="
                                                        row.valid
                                                            ? 'Válido'
                                                            : 'Error'
                                                    "
                                                    :tone="
                                                        row.valid
                                                            ? 'green'
                                                            : 'red'
                                                    "
                                                />
                                            </td>
                                            <td
                                                class="px-3 py-2.5 font-mono text-xs text-slate-700"
                                            >
                                                {{ row.raw.code || 'Auto' }}
                                            </td>
                                            <td
                                                class="px-3 py-2.5 text-xs font-medium text-slate-900"
                                            >
                                                {{ row.raw.first_name }}
                                                {{ row.raw.last_name }}
                                            </td>
                                            <td
                                                class="px-3 py-2.5 text-xs text-slate-600"
                                            >
                                                {{ row.raw.area || '—' }} ·
                                                {{ row.raw.position || '—' }}
                                            </td>
                                            <td class="px-3 py-2.5 text-xs">
                                                <span
                                                    v-if="row.valid"
                                                    class="inline-flex items-center gap-1 text-emerald-700"
                                                >
                                                    <CheckCircle2
                                                        class="size-3.5"
                                                    />
                                                    Sin observaciones
                                                </span>
                                                <ul
                                                    v-else
                                                    class="list-disc space-y-0.5 pl-4 text-red-700"
                                                >
                                                    <li
                                                        v-for="(
                                                            err, idx
                                                        ) in row.errors"
                                                        :key="idx"
                                                    >
                                                        {{ err }}
                                                    </li>
                                                </ul>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                <div
                    class="flex items-center justify-between gap-3 border-t border-slate-100 px-6 py-4"
                >
                    <p class="text-[11px] text-slate-500">
                        <template v-if="preview">
                            Se importarán únicamente los registros válidos; las
                            filas con error serán omitidas.
                        </template>
                    </p>
                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            class="h-10 rounded-lg px-4 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                            @click="close"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            :disabled="importing || validPayloads.length === 0"
                            class="h-10 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white transition hover:bg-[#1bb2b4] disabled:cursor-not-allowed disabled:opacity-50"
                            @click="confirmImport"
                        >
                            {{
                                importing
                                    ? 'Importando...'
                                    : `Importar ${validPayloads.length} válido(s)`
                            }}
                        </button>
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
