<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    DollarSign,
    Info,
    RotateCcw,
    SlidersHorizontal,
    TrendingDown,
    TrendingUp,
    X,
} from '@lucide/vue';
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
const bonus = ref(0);
const customPerceptions = ref(0);
const customDeductions = ref(0);
const adjustmentReason = ref('');
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
        bonus.value = emp.concepts?.bonus ?? 0;
        customPerceptions.value = emp.concepts?.customPerceptions ?? 0;
        customDeductions.value = emp.concepts?.customDeductions ?? 0;
        adjustmentReason.value = emp.concepts?.adjustmentReason ?? '';
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
        bonus: bonus.value,
        customPerceptions: customPerceptions.value,
        customDeductions: customDeductions.value,
        adjustmentReason: adjustmentReason.value,
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
    bonus.value = props.employee.concepts?.bonus ?? 0;
    customPerceptions.value = 0;
    customDeductions.value = 0;
    adjustmentReason.value = '';
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
                                    <SlidersHorizontal class="size-4" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        Ajuste Manual por Concepto
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Modifica incidencias y montos de pre-nómina
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar panel"
                                @click="emit('close')"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Body (Scrollable) -->
                        <div
                            class="flex-1 space-y-6 overflow-y-auto px-6 py-5 text-xs text-slate-700"
                        >
                            <!-- Tarjeta de Identificación del Empleado -->
                            <div
                                class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/80 p-3.5"
                            >
                                <div class="flex items-center gap-3">
                                    <div
                                        class="flex size-10 items-center justify-center rounded-full bg-slate-900 text-xs font-semibold text-white"
                                    >
                                        {{ employee.initials ?? 'EM' }}
                                    </div>
                                    <div>
                                        <p class="font-semibold text-slate-900 text-sm">
                                            {{ employee.name }}
                                        </p>
                                        <p class="text-[11px] text-slate-500">
                                            {{ employee.area ?? 'General' }} • Salario Diario: {{ formatCurrency(employee.dailyRate) }}
                                        </p>
                                    </div>
                                </div>
                                <NexusBadge :tone="previewRow.tone">
                                    {{ previewRow.status }}
                                </NexusBadge>
                            </div>

                            <!-- Sección 1: Asistencias e Incidencias Base -->
                            <div class="space-y-3">
                                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    1. Asistencias e Incidencias
                                </h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block font-medium text-slate-700">
                                            Días trabajados
                                        </label>
                                        <input
                                            v-model.number="days"
                                            type="number"
                                            min="0"
                                            max="31"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block font-medium text-slate-700">
                                            Horas extra
                                        </label>
                                        <input
                                            v-model.number="extra"
                                            type="number"
                                            step="0.5"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                        />
                                    </div>
                                    <div>
                                        <label class="mb-1 block font-medium text-slate-700">
                                            Retardos registrados
                                        </label>
                                        <input
                                            v-model.number="delays"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                        />
                                        <span class="text-[10px] text-slate-400">$50 MXN sanción c/u</span>
                                    </div>
                                    <div>
                                        <label class="mb-1 block font-medium text-slate-700">
                                            Faltas injustificadas
                                        </label>
                                        <input
                                            v-model.number="absences"
                                            type="number"
                                            min="0"
                                            class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                        />
                                        <span class="text-[10px] text-slate-400">Descuento de día completo</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Sección 2: Ajuste Manual de Conceptos Financieros -->
                            <div class="space-y-3">
                                <h4 class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                    2. Conceptos Extras y Ajustes Manuales
                                </h4>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block font-medium text-emerald-800 flex items-center gap-1">
                                            <TrendingUp class="size-3.5 text-emerald-600" />
                                            Bono de Desempeño
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-slate-400">$</span>
                                            <input
                                                v-model.number="bonus"
                                                type="number"
                                                min="0"
                                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1 block font-medium text-emerald-800 flex items-center gap-1">
                                            <DollarSign class="size-3.5 text-emerald-600" />
                                            Percepciones Extras
                                        </label>
                                        <div class="relative">
                                            <span class="absolute left-3 top-2 text-slate-400">$</span>
                                            <input
                                                v-model.number="customPerceptions"
                                                type="number"
                                                min="0"
                                                placeholder="0"
                                                class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block font-medium text-rose-800 flex items-center gap-1">
                                        <TrendingDown class="size-3.5 text-rose-600" />
                                        Deducciones Extras Manuales
                                    </label>
                                    <div class="relative">
                                        <span class="absolute left-3 top-2 text-slate-400">$</span>
                                        <input
                                            v-model.number="customDeductions"
                                            type="number"
                                            min="0"
                                            placeholder="0"
                                            class="w-full rounded-lg border border-slate-200 pl-7 pr-3 py-2 text-sm text-slate-800 focus:border-rose-400 focus:outline-none focus:ring-1 focus:ring-rose-400"
                                        />
                                    </div>
                                    <span class="text-[10px] text-slate-400">Préstamos, anticipos o cargos administrativos</span>
                                </div>

                                <div>
                                    <label class="mb-1 block font-medium text-slate-700">
                                        Motivo del ajuste / Observaciones
                                    </label>
                                    <textarea
                                        v-model="adjustmentReason"
                                        rows="2"
                                        placeholder="Ej. Se autoriza compensación de transporte o corrección de chequeo..."
                                        class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800 focus:border-[#23c6c8] focus:outline-none focus:ring-1 focus:ring-[#23c6c8]"
                                    ></textarea>
                                </div>
                            </div>

                            <!-- Sección 3: Resumen del Cálculo en Vivo -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2.5">
                                <h4 class="font-semibold text-slate-800 flex items-center gap-1.5 text-xs">
                                    <Info class="size-3.5 text-[#139b9e]" />
                                    Desglose de cálculo en vivo
                                </h4>
                                <div class="space-y-1.5 border-t border-slate-200 pt-2 text-slate-600 text-[11px]">
                                    <div class="flex justify-between">
                                        <span>Sueldo Base ({{ days }} días):</span>
                                        <span class="font-medium text-slate-900">{{ formatCurrency(previewRow.concepts?.salary ?? 0) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Horas Extra ({{ extra }}h):</span>
                                        <span class="font-medium text-slate-900">+{{ formatCurrency(previewRow.concepts?.overtime ?? 0) }}</span>
                                    </div>
                                    <div v-if="bonus > 0" class="flex justify-between text-emerald-700">
                                        <span>Bono de Desempeño:</span>
                                        <span class="font-medium">+{{ formatCurrency(bonus) }}</span>
                                    </div>
                                    <div v-if="customPerceptions > 0" class="flex justify-between text-emerald-700">
                                        <span>Percepción Extra:</span>
                                        <span class="font-medium">+{{ formatCurrency(customPerceptions) }}</span>
                                    </div>
                                    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold text-emerald-800">
                                        <span>Total Percepciones:</span>
                                        <span>{{ formatCurrency(previewRow.perceptions) }}</span>
                                    </div>

                                    <div class="flex justify-between pt-1">
                                        <span>Retención ISR estimada:</span>
                                        <span class="text-rose-600">-{{ formatCurrency(previewRow.isr ?? previewRow.concepts?.isr ?? 0) }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Aportación IMSS estimada:</span>
                                        <span class="text-rose-600">-{{ formatCurrency(previewRow.imss ?? previewRow.concepts?.imss ?? 0) }}</span>
                                    </div>
                                    <div v-if="delays > 0" class="flex justify-between text-rose-600">
                                        <span>Sanción Retardos ({{ delays }}):</span>
                                        <span>-{{ formatCurrency(delays * 50) }}</span>
                                    </div>
                                    <div v-if="absences > 0" class="flex justify-between text-rose-600">
                                        <span>Descuento Faltas ({{ absences }}):</span>
                                        <span>-{{ formatCurrency(previewRow.concepts?.absencesPenalty ?? 0) }}</span>
                                    </div>
                                    <div v-if="customDeductions > 0" class="flex justify-between text-rose-600">
                                        <span>Deducción Extra Manual:</span>
                                        <span>-{{ formatCurrency(customDeductions) }}</span>
                                    </div>
                                    <div class="flex justify-between border-t border-slate-200 pt-1 font-semibold text-rose-800">
                                        <span>Total Deducciones:</span>
                                        <span>{{ formatCurrency(previewRow.deductions) }}</span>
                                    </div>

                                    <div class="flex justify-between border-t-2 border-slate-300 pt-2 text-sm font-bold text-slate-900">
                                        <span>Neto a Pagar:</span>
                                        <span class="text-[#0a161e]">{{ formatCurrency(previewRow.net) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div
                            class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 px-6 py-4"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-500 hover:text-slate-800"
                                @click="resetDefaults"
                            >
                                <RotateCcw class="size-3.5" />
                                Restablecer
                            </button>
                            <div class="flex items-center gap-2">
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
                                    Guardar Ajuste
                                </button>
                            </div>
                        </div>
                    </aside>
                </NexusViewTransition>
            </div>
        </Transition>
    </Teleport>
</template>
