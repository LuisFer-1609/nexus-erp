<script setup lang="ts">
import { Calendar, CheckCircle2, PlusCircle, X } from '@lucide/vue';
import { ElOption, ElSelect } from 'element-plus';
import { ref } from 'vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';

const props = defineProps<{
    open: boolean;
    frequencies?: Array<{ id: string; name: string }>;
}>();

const emit = defineEmits<{
    close: [];
    create: [
        payload: {
            title: string;
            type: string;
            frequency: string;
            startDate: string;
            endDate: string;
            paymentDate: string;
            groupBy: string;
            annualTaxAdjustment: boolean;
        },
    ];
}>();

const title = ref('');
const type = ref('Nómina ordinaria');
const frequency = ref('Quincenal');
const startDate = ref('2026-10-01');
const endDate = ref('2026-10-15');
const paymentDate = ref('2026-10-15');
const groupBy = ref('Empleado');
const annualTaxAdjustment = ref(false);

const handleFrequencyChange = (val: string): void => {
    frequency.value = val;
    if (val === 'Semanal') {
        startDate.value = '2026-10-01';
        endDate.value = '2026-10-07';
        paymentDate.value = '2026-10-07';
    } else if (val === 'Quincenal') {
        startDate.value = '2026-10-01';
        endDate.value = '2026-10-15';
        paymentDate.value = '2026-10-15';
    } else if (val === 'Mensual') {
        startDate.value = '2026-10-01';
        endDate.value = '2026-10-31';
        paymentDate.value = '2026-10-31';
    }
};

const handleSave = (): void => {
    emit('create', {
        title: title.value.trim() || `Nómina ${frequency.value} ${startDate.value}`,
        type: type.value,
        frequency: frequency.value,
        startDate: startDate.value,
        endDate: endDate.value,
        paymentDate: paymentDate.value,
        groupBy: groupBy.value,
        annualTaxAdjustment: annualTaxAdjustment.value,
    });
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex justify-end bg-slate-950/40 backdrop-blur-[2px]"
                @click.self="emit('close')"
            >
                <NexusViewTransition direction="left" appear>
                    <aside
                        class="flex h-full w-full max-w-lg flex-col border-l border-slate-200 bg-white shadow-2xl"
                    >
                        <!-- Header -->
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-6 py-5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-[#139b9e]"
                                >
                                    <PlusCircle class="size-4" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Nueva Pre-Nómina
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Configura el período y tipo de cálculo
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar modal"
                                @click="emit('close')"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Form Content -->
                        <div
                            class="flex-1 space-y-5 overflow-y-auto px-6 py-5 text-xs text-slate-700"
                        >
                            <div>
                                <label class="mb-1 block font-medium text-slate-700">
                                    Título / Identificador
                                </label>
                                <input
                                    v-model="title"
                                    type="text"
                                    placeholder="Ej. Nómina 1ra Quincena Octubre 2026"
                                    class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Tipo de Pre-Nómina
                                    </label>
                                    <ElSelect v-model="type" class="w-full">
                                        <ElOption
                                            label="Nómina Ordinaria"
                                            value="Nómina ordinaria"
                                        />
                                        <ElOption
                                            label="Nómina Extraordinaria"
                                            value="Nómina extraordinaria"
                                        />
                                        <ElOption
                                            label="Finiquito Ordinario"
                                            value="Finiquito Ordinario"
                                        />
                                        <ElOption
                                            label="Aguinaldo / Bonos"
                                            value="Aguinaldo"
                                        />
                                    </ElSelect>
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Periodicidad de Pago
                                    </label>
                                    <ElSelect
                                        :model-value="frequency"
                                        class="w-full"
                                        @change="handleFrequencyChange"
                                    >
                                        <ElOption label="Semanal" value="Semanal" />
                                        <ElOption label="Quincenal" value="Quincenal" />
                                        <ElOption label="Mensual" value="Mensual" />
                                        <ElOption label="Otra Periodicidad" value="Otra Periodicidad" />
                                    </ElSelect>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Fecha Inicio
                                    </label>
                                    <input
                                        v-model="startDate"
                                        type="date"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-800 focus:border-[#23c6c8] focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Fecha Fin
                                    </label>
                                    <input
                                        v-model="endDate"
                                        type="date"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-800 focus:border-[#23c6c8] focus:outline-none"
                                    />
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Fecha de Pago
                                    </label>
                                    <input
                                        v-model="paymentDate"
                                        type="date"
                                        class="w-full rounded-lg border border-slate-200 px-2 py-1.5 text-xs text-slate-800 focus:border-[#23c6c8] focus:outline-none"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-2">
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Agrupamiento
                                    </label>
                                    <ElSelect v-model="groupBy" class="w-full">
                                        <ElOption label="Por Empleado" value="Empleado" />
                                        <ElOption label="Por Área / Departamento" value="Area" />
                                        <ElOption label="Todos" value="Todos" />
                                    </ElSelect>
                                </div>
                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Ajuste Anual ISR
                                    </label>
                                    <ElSelect
                                        :model-value="annualTaxAdjustment ? '1' : '0'"
                                        class="w-full"
                                        @change="(val: string) => annualTaxAdjustment = val === '1'"
                                    >
                                        <ElOption label="No aplicar ajuste anual" value="0" />
                                        <ElOption label="Sí, aplicar ajuste anual" value="1" />
                                    </ElSelect>
                                </div>
                            </div>

                            <!-- Resumen del periodo a generar -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <h4 class="font-semibold text-slate-800 flex items-center gap-1.5 text-xs mb-2">
                                    <Calendar class="size-3.5 text-[#139b9e]" />
                                    Resumen del ciclo a procesar
                                </h4>
                                <p class="text-[11px] text-slate-600 leading-relaxed">
                                    Se generará el registro del período y se calcularán automáticamente las asistencias, incidencias (retardos, faltas, horas extra) y el desglose de percepciones y retenciones de ley (ISR e IMSS) para todos los colaboradores activos.
                                </p>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4"
                        >
                            <button
                                type="button"
                                class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                @click="emit('close')"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-[#23c6c8] px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-[#1eb0b2] focus:outline-none focus:ring-2 focus:ring-[#23c6c8]/40"
                                @click="handleSave"
                            >
                                <CheckCircle2 class="size-3.5" />
                                Crear y Calcular Pre-Nómina
                            </button>
                        </div>
                    </aside>
                </NexusViewTransition>
            </div>
        </Transition>
    </Teleport>
</template>
