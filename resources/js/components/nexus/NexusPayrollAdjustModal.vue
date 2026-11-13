<script setup lang="ts">
import { CheckCircle2, RotateCcw, SlidersHorizontal, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';
import {
    formatCurrency,
    recalculateEmployeeRow,
    type PayrollRow,
} from '@/lib/payrollExport';

const props = defineProps<{
    open: boolean;
    employee: PayrollRow | null;
}>();

const emit = defineEmits<{
    close: [];
    save: [updated: PayrollRow];
}>();

const days = ref(10);
const delays = ref(0);
const absences = ref(0);
const extra = ref(0);
const statusOverride = ref<'auto' | 'Correcto' | 'Revisar'>('auto');

watch(
    () => props.employee,
    (emp) => {
        if (!emp) {
            return;
        }
        days.value = emp.days;
        delays.value = emp.delays;
        absences.value = emp.absences;
        extra.value = emp.extra;
        statusOverride.value = 'auto';
    },
    { immediate: true },
);

const previewRow = computed<PayrollRow | null>(() => {
    if (!props.employee) {
        return null;
    }
    return recalculateEmployeeRow(props.employee, {
        days: days.value,
        delays: delays.value,
        absences: absences.value,
        extra: extra.value,
        status:
            statusOverride.value === 'auto' ? undefined : statusOverride.value,
    });
});

const resetDefaults = (): void => {
    if (!props.employee) {
        return;
    }
    days.value = props.employee.days;
    delays.value = props.employee.delays;
    absences.value = props.employee.absences;
    extra.value = props.employee.extra;
    statusOverride.value = 'auto';
};

const handleSave = (): void => {
    if (!previewRow.value) {
        return;
    }
    emit('save', previewRow.value);
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
                v-if="open && employee && previewRow"
                class="fixed inset-0 z-50 flex justify-end bg-slate-950/35 backdrop-blur-[2px]"
                @click.self="emit('close')"
            >
                <NexusViewTransition direction="left" appear>
                    <aside
                        class="flex h-full w-full max-w-md flex-col border-l border-slate-200 bg-white shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-6 py-5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-[#139b9e]"
                                >
                                    <SlidersHorizontal class="size-4" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Ajustar pre-nómina
                                    </h3>
                                    <p class="text-[11px] text-slate-500">
                                        {{ employee.name }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar panel"
                                @click="emit('close')"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <div class="flex-1 space-y-5 overflow-y-auto p-6">
                            <div
                                class="grid grid-cols-2 gap-4 rounded-xl border border-slate-200/80 bg-slate-50/70 p-4"
                            >
                                <div>
                                    <p
                                        class="text-[10px] font-medium text-slate-500"
                                    >
                                        Percepción estimada
                                    </p>
                                    <NexusViewTransition
                                        direction="up"
                                        mode="out-in"
                                    >
                                        <p
                                            :key="previewRow.perceptions"
                                            class="mt-0.5 text-base font-bold text-emerald-600"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewRow.perceptions,
                                                )
                                            }}
                                        </p>
                                    </NexusViewTransition>
                                </div>
                                <div>
                                    <p
                                        class="text-[10px] font-medium text-slate-500"
                                    >
                                        Deducción aplicada
                                    </p>
                                    <NexusViewTransition
                                        direction="down"
                                        mode="out-in"
                                    >
                                        <p
                                            :key="previewRow.deductions"
                                            class="mt-0.5 text-base font-bold text-rose-500"
                                        >
                                            {{
                                                formatCurrency(
                                                    previewRow.deductions,
                                                )
                                            }}
                                        </p>
                                    </NexusViewTransition>
                                </div>
                                <div
                                    class="col-span-2 flex items-center justify-between border-t border-slate-200/80 pt-3"
                                >
                                    <div>
                                        <p
                                            class="text-[10px] font-medium text-slate-500"
                                        >
                                            Neto resultante
                                        </p>
                                        <NexusViewTransition
                                            direction="up"
                                            mode="out-in"
                                        >
                                            <p
                                                :key="previewRow.net"
                                                class="text-lg font-bold text-slate-900"
                                            >
                                                {{
                                                    formatCurrency(
                                                        previewRow.net,
                                                    )
                                                }}
                                            </p>
                                        </NexusViewTransition>
                                    </div>
                                    <NexusBadge
                                        :label="previewRow.status"
                                        :tone="previewRow.tone"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <label class="block">
                                    <span
                                        class="mb-1.5 block text-[11px] font-medium text-slate-700"
                                    >
                                        Días trabajados
                                    </span>
                                    <input
                                        v-model.number="days"
                                        type="number"
                                        min="0"
                                        max="15"
                                        step="1"
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-800 transition outline-none focus:border-[#23c6c8] focus:ring-2 focus:ring-[#23c6c8]/15"
                                    />
                                </label>

                                <label class="block">
                                    <span
                                        class="mb-1.5 block text-[11px] font-medium text-slate-700"
                                    >
                                        Horas extra
                                    </span>
                                    <input
                                        v-model.number="extra"
                                        type="number"
                                        min="0"
                                        max="40"
                                        step="0.5"
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-800 transition outline-none focus:border-[#23c6c8] focus:ring-2 focus:ring-[#23c6c8]/15"
                                    />
                                </label>

                                <label class="block">
                                    <span
                                        class="mb-1.5 block text-[11px] font-medium text-slate-700"
                                    >
                                        Retardos (Ret.)
                                    </span>
                                    <input
                                        v-model.number="delays"
                                        type="number"
                                        min="0"
                                        max="15"
                                        step="1"
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-800 transition outline-none focus:border-[#23c6c8] focus:ring-2 focus:ring-[#23c6c8]/15"
                                    />
                                </label>

                                <label class="block">
                                    <span
                                        class="mb-1.5 block text-[11px] font-medium text-slate-700"
                                    >
                                        Faltas
                                    </span>
                                    <input
                                        v-model.number="absences"
                                        type="number"
                                        min="0"
                                        max="15"
                                        step="1"
                                        class="h-9 w-full rounded-lg border border-slate-200 bg-white px-3 text-xs text-slate-800 transition outline-none focus:border-[#23c6c8] focus:ring-2 focus:ring-[#23c6c8]/15"
                                    />
                                </label>
                            </div>

                            <div>
                                <span
                                    class="mb-2 block text-[11px] font-medium text-slate-700"
                                >
                                    Validación de estado
                                </span>
                                <div class="grid grid-cols-3 gap-2">
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg border text-[11px] font-medium transition"
                                        :class="
                                            statusOverride === 'auto'
                                                ? 'border-[#23c6c8] bg-cyan-50/60 text-[#139b9e]'
                                                : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                        "
                                        @click="statusOverride = 'auto'"
                                    >
                                        Automático
                                    </button>
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg border text-[11px] font-medium transition"
                                        :class="
                                            statusOverride === 'Correcto'
                                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                        "
                                        @click="statusOverride = 'Correcto'"
                                    >
                                        Correcto
                                    </button>
                                    <button
                                        type="button"
                                        class="h-8 rounded-lg border text-[11px] font-medium transition"
                                        :class="
                                            statusOverride === 'Revisar'
                                                ? 'border-amber-500 bg-amber-50 text-amber-700'
                                                : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                                        "
                                        @click="statusOverride = 'Revisar'"
                                    >
                                        Revisar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-6 py-4"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 transition hover:text-slate-800"
                                @click="resetDefaults"
                            >
                                <RotateCcw class="size-3.5" />
                                Restablecer
                            </button>

                            <div class="flex gap-2">
                                <button
                                    type="button"
                                    class="h-9 rounded-lg border border-slate-200 bg-white px-4 text-xs font-semibold text-slate-700 transition hover:bg-slate-50"
                                    @click="emit('close')"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white shadow-xs transition hover:bg-[#1db2b4]"
                                    @click="handleSave"
                                >
                                    <CheckCircle2 class="size-3.5" />
                                    Guardar ajuste
                                </button>
                            </div>
                        </div>
                    </aside>
                </NexusViewTransition>
            </div>
        </Transition>
    </Teleport>
</template>
