<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Download,
    Filter,
    PlusCircle,
    RefreshCw,
    SlidersHorizontal,
} from '@lucide/vue';
import { ElOption, ElSelect } from 'element-plus';
import { computed, onMounted, ref, watch } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusNewPayrollModal from '@/components/nexus/NexusNewPayrollModal.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import NexusPayrollAdjustModal from '@/components/nexus/NexusPayrollAdjustModal.vue';
import NexusPreNominaSkeleton from '@/components/nexus/NexusPreNominaSkeleton.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';
import {
    exportPayrollToCsv,
    formatCurrency,
    recalculateEmployeeRow,
    type PayrollPeriod,
    type PayrollRow,
    type PayrollSummary,
} from '@/lib/payrollExport';

const props = withDefaults(
    defineProps<{
        selectedPeriodId?: string;
        selectedFrequency?: string;
        frequencies?: Array<{ id: string; name: string }>;
        periods?: Array<{
            id: string;
            code?: string;
            label: string;
            frequency?: string;
            type?: string;
            status: string;
            tone: 'amber' | 'green' | 'cyan' | 'slate';
            startDate?: string;
            endDate?: string;
            paymentDate?: string;
        }>;
        currentPeriod?: PayrollPeriod | null;
    }>(),
    {
        selectedPeriodId: '',
        selectedFrequency: 'todas',
        frequencies: () => [
            { id: 'todas', name: 'Todas las frecuencias' },
            { id: 'semanal', name: 'Semanal' },
            { id: 'quincenal', name: 'Quincenal' },
            { id: 'mensual', name: 'Mensual' },
        ],
        periods: () => [],
        currentPeriod: null,
    },
);

const activePeriodId = ref(props.selectedPeriodId);
const filterFrequency = ref(props.selectedFrequency);
const searchQuery = ref('');
const filterStatus = ref<'todas' | 'Correcto' | 'Revisar'>('todas');
const isLoading = ref(true);
const isRecalculating = ref(false);
const transitionDirection = ref<'up' | 'down' | 'left' | 'right'>('up');
const feedbackBanner = ref<string | null>(null);
const selectedEmployee = ref<PayrollRow | null>(null);
const adjustModalOpen = ref(false);
const newPayrollModalOpen = ref(false);

const localPeriods = ref([...props.periods]);

const payrollRows = ref<PayrollRow[]>(
    props.currentPeriod?.rows.map((r) => ({ ...r })) ?? [],
);

watch(
    () => props.currentPeriod,
    (nextPeriod) => {
        payrollRows.value = nextPeriod?.rows.map((r) => ({ ...r })) ?? [];
    },
);

watch(
    () => props.periods,
    (next) => {
        localPeriods.value = [...next];
    },
);

const filteredPeriods = computed(() => {
    if (filterFrequency.value === 'todas') {
        return localPeriods.value;
    }
    return localPeriods.value.filter(
        (p) =>
            p.frequency?.toLowerCase() ===
            filterFrequency.value.toLowerCase(),
    );
});

const currentPeriodMeta = computed(
    () =>
        localPeriods.value.find((p) => p.id === activePeriodId.value) ??
        props.currentPeriod,
);

const displayedRows = computed(() => {
    return payrollRows.value.filter((row) => {
        const matchesSearch =
            searchQuery.value === '' ||
            row.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            (row.area &&
                row.area.toLowerCase().includes(searchQuery.value.toLowerCase()));

        const matchesStatus =
            filterStatus.value === 'todas' || row.status === filterStatus.value;

        return matchesSearch && matchesStatus;
    });
});

const computedSummary = computed<PayrollSummary>(() => {
    const perceptions = payrollRows.value.reduce(
        (sum, r) => sum + r.perceptions,
        0,
    );
    const deductions = payrollRows.value.reduce(
        (sum, r) => sum + r.deductions,
        0,
    );
    const isr = payrollRows.value.reduce(
        (sum, r) => sum + (r.isr ?? r.concepts?.isr ?? 0),
        0,
    );
    const imss = payrollRows.value.reduce(
        (sum, r) => sum + (r.imss ?? r.concepts?.imss ?? 0),
        0,
    );
    const net = Math.max(0, perceptions - deductions);

    return {
        employees: payrollRows.value.length,
        perceptions,
        deductions,
        isr,
        imss,
        net,
    };
});

const showFeedback = (message: string): void => {
    feedbackBanner.value = message;
    window.setTimeout(() => {
        if (feedbackBanner.value === message) {
            feedbackBanner.value = null;
        }
    }, 3400);
};

const handlePeriodChange = (nextId: string | number | boolean): void => {
    const periodKey = String(nextId);
    const prevIndex = localPeriods.value.findIndex(
        (p) => p.id === activePeriodId.value,
    );
    const nextIndex = localPeriods.value.findIndex((p) => p.id === periodKey);
    transitionDirection.value = nextIndex >= prevIndex ? 'left' : 'right';

    isLoading.value = true;
    activePeriodId.value = periodKey;

    router.get(
        '/pre-nomina',
        { period: periodKey },
        {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => {
                isLoading.value = false;
            },
        },
    );
};

const handleRecalculate = (): void => {
    isRecalculating.value = true;
    isLoading.value = true;
    transitionDirection.value = 'up';

    window.setTimeout(() => {
        payrollRows.value = payrollRows.value.map((row) =>
            recalculateEmployeeRow(row),
        );
        isLoading.value = false;
        isRecalculating.value = false;
        showFeedback('Pre-nómina recalculada con asistencias y tabuladores actualizados.');
    }, 450);
};

const handleExport = (): void => {
    if (payrollRows.value.length === 0) {
        return;
    }

    exportPayrollToCsv(
        currentPeriodMeta.value?.label ?? 'Periodo',
        payrollRows.value,
        computedSummary.value,
    );
    showFeedback('Reporte detallado de pre-nómina exportado correctamente en CSV.');
};

const openAdjustModal = (row: PayrollRow): void => {
    selectedEmployee.value = row;
    adjustModalOpen.value = true;
};

const saveAdjustedEmployee = (updated: PayrollRow): void => {
    const idx = payrollRows.value.findIndex((r) => r.id === updated.id);
    if (idx !== -1) {
        payrollRows.value[idx] = updated;
    }
    adjustModalOpen.value = false;
    showFeedback(`Ajustes manuales aplicados con éxito a ${updated.name}.`);
};

const handleCreatePayrollPeriod = (payload: {
    title: string;
    type: string;
    frequency: string;
    startDate: string;
    endDate: string;
    paymentDate: string;
    groupBy: string;
    annualTaxAdjustment: boolean;
}): void => {
    const newId = String(Date.now());
    const newPeriod = {
        id: newId,
        code: `NOM-${Math.floor(240 + Math.random() * 50)}`,
        label: `${payload.startDate} — ${payload.endDate}`,
        frequency: payload.frequency,
        type: payload.type,
        status: 'Borrador',
        tone: 'amber' as const,
        startDate: payload.startDate,
        endDate: payload.endDate,
        paymentDate: payload.paymentDate,
    };

    localPeriods.value.unshift(newPeriod);
    activePeriodId.value = newId;
    newPayrollModalOpen.value = false;

    // Recalcular filas adaptadas al nuevo período
    payrollRows.value = payrollRows.value.map((row) =>
        recalculateEmployeeRow(row, {
            days: payload.frequency === 'Semanal' ? 6 : payload.frequency === 'Mensual' ? 30 : 15,
        }),
    );

    showFeedback(`Pre-Nómina "${payload.title}" creada y calculada exitosamente.`);
};

onMounted(() => {
    window.setTimeout(() => {
        isLoading.value = false;
    }, 300);
});
</script>

<template>
    <Head title="Pre-nómina — NexusERP" />

    <div class="space-y-6">
        <!-- Toast feedback banner -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="-translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="feedbackBanner"
                class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50/90 px-4 py-2.5 text-xs font-medium text-emerald-800 shadow-sm"
                role="status"
            >
                <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                <span>{{ feedbackBanner }}</span>
            </div>
        </Transition>

        <!-- Page Header -->
        <NexusPageHeader
            category="Recursos Humanos / Nómina"
            title="Pre-nómina"
            description="Cálculo integral de percepciones, deducciones (ISR, IMSS) e incidencias a partir de asistencias registradas."
        />

        <!-- Top Controls Bar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-3">
                <!-- Selector de Frecuencia -->
                <div class="w-44">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Periodicidad
                    </label>
                    <ElSelect
                        v-model="filterFrequency"
                        class="w-full"
                        size="default"
                    >
                        <ElOption
                            v-for="freq in frequencies"
                            :key="freq.id"
                            :label="freq.name"
                            :value="freq.id"
                        />
                    </ElSelect>
                </div>

                <!-- Selector de Periodo con Element Plus -->
                <div class="w-64">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Periodo de Nómina
                    </label>
                    <ElSelect
                        :model-value="activePeriodId"
                        class="w-full"
                        size="default"
                        @change="handlePeriodChange"
                    >
                        <ElOption
                            v-for="p in filteredPeriods"
                            :key="p.id"
                            :label="`${p.code ? p.code + ' • ' : ''}${p.label}`"
                            :value="p.id"
                        />
                    </ElSelect>
                </div>

                <div v-if="currentPeriodMeta" class="self-end pb-1.5 flex items-center gap-2">
                    <NexusBadge :tone="currentPeriodMeta.tone">
                        {{ currentPeriodMeta.status }}
                    </NexusBadge>
                    <span v-if="currentPeriodMeta.frequency" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-medium text-slate-600">
                        {{ currentPeriodMeta.frequency }}
                    </span>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900/30"
                    @click="newPayrollModalOpen = true"
                >
                    <PlusCircle class="size-3.5 text-[#23c6c8]" />
                    Nueva Pre-Nómina
                </button>

                <button
                    type="button"
                    :disabled="isRecalculating"
                    class="inline-flex items-center gap-2 rounded-xl bg-[#23c6c8] px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-[#1eb0b2] focus:outline-none focus:ring-2 focus:ring-[#23c6c8]/40 disabled:opacity-50"
                    @click="handleRecalculate"
                >
                    <RefreshCw
                        class="size-3.5"
                        :class="{ 'animate-spin': isRecalculating }"
                    />
                    Recalcular
                </button>

                <button
                    type="button"
                    :disabled="payrollRows.length === 0"
                    class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-xs transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300/40 disabled:opacity-50"
                    @click="handleExport"
                >
                    <Download class="size-3.5 text-slate-500" />
                    Exportar reporte
                </button>
            </div>
        </div>

        <!-- Skeleton loader -->
        <NexusPreNominaSkeleton v-if="isLoading" />

        <!-- Main Content -->
        <NexusViewTransition
            v-else
            :direction="transitionDirection"
            mode="out-in"
        >
            <div :key="activePeriodId" class="space-y-6">
                <!-- KPI Summary Cards -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <NexusKpiCard
                        label="Empleados"
                        :value="computedSummary.employees"
                        meta="En ciclo de pago"
                        color="cyan"
                    />
                    <NexusKpiCard
                        label="Percepciones Brutas"
                        :value="formatCurrency(computedSummary.perceptions)"
                        meta="Sueldos, horas extra y bonos"
                        color="green"
                    />
                    <NexusKpiCard
                        label="Deducciones e Impuestos"
                        :value="formatCurrency(computedSummary.deductions)"
                        :meta="`ISR: ${formatCurrency(computedSummary.isr ?? 0)} • IMSS: ${formatCurrency(computedSummary.imss ?? 0)}`"
                        color="rose"
                    />
                    <NexusKpiCard
                        label="Neto Estimado"
                        :value="formatCurrency(computedSummary.net)"
                        meta="A dispersar a colaboradores"
                        color="purple"
                    />
                </div>

                <!-- Detalle por Empleado Table -->
                <div
                    class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs"
                >
                    <!-- Table Header & Filters -->
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Detalle por empleado y conceptos
                            </h2>
                            <p class="text-xs text-slate-500">
                                Desglose de percepciones, deducciones de ley e incidencias. Los valores pueden ajustarse manualmente antes de la nómina final.
                            </p>
                        </div>

                        <!-- Filtro por nombre y estado -->
                        <div class="flex items-center gap-3">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar colaborador..."
                                class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-[#23c6c8] focus:outline-none"
                            />
                            <ElSelect
                                v-model="filterStatus"
                                size="small"
                                class="w-32"
                            >
                                <ElOption label="Todos" value="todas" />
                                <ElOption label="Correcto" value="Correcto" />
                                <ElOption label="Revisar" value="Revisar" />
                            </ElSelect>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-if="displayedRows.length === 0"
                        class="p-8"
                    >
                        <NexusEmptyState
                            title="No hay empleados en este período"
                            description="Intenta seleccionar otra periodicidad o agrega una nueva pre-nómina."
                        />
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead
                                class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-semibold text-slate-500"
                            >
                                <tr>
                                    <th class="py-3.5 pl-6 pr-3">Empleado</th>
                                    <th class="px-2 py-3.5 text-center">Días</th>
                                    <th class="px-2 py-3.5 text-center">Ret.</th>
                                    <th class="px-2 py-3.5 text-center">Faltas</th>
                                    <th class="px-2 py-3.5 text-center">H. extra</th>
                                    <th class="px-3 py-3.5 text-right">Percepciones</th>
                                    <th class="px-3 py-3.5 text-right">ISR</th>
                                    <th class="px-3 py-3.5 text-right">IMSS</th>
                                    <th class="px-3 py-3.5 text-right">Deducciones</th>
                                    <th class="px-3 py-3.5 text-right">Neto</th>
                                    <th class="px-3 py-3.5 text-center">Estado</th>
                                    <th class="py-3.5 pl-3 pr-6 text-right">Acción</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 text-slate-600">
                                <tr
                                    v-for="row in displayedRows"
                                    :key="row.id"
                                    class="transition hover:bg-slate-50/60"
                                >
                                    <td class="py-3.5 pl-6 pr-3">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                            >
                                                {{ row.initials ?? 'EM' }}
                                            </div>
                                            <div>
                                                <p
                                                    class="font-medium text-slate-900"
                                                >
                                                    {{ row.name }}
                                                </p>
                                                <p class="text-[10px] text-slate-400">
                                                    {{ row.area ?? 'General' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 py-3.5 text-center font-medium text-slate-700">
                                        {{ row.days }}
                                    </td>
                                    <td class="px-2 py-3.5 text-center">
                                        <span
                                            :class="
                                                row.delays > 0
                                                    ? 'font-semibold text-amber-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{ row.delays }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-3.5 text-center">
                                        <span
                                            :class="
                                                row.absences > 0
                                                    ? 'font-semibold text-rose-600'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{ row.absences }}
                                        </span>
                                    </td>
                                    <td class="px-2 py-3.5 text-center">
                                        <span
                                            :class="
                                                row.extra > 0
                                                    ? 'font-semibold text-cyan-700'
                                                    : 'text-slate-400'
                                            "
                                        >
                                            {{ row.extra > 0 ? `${row.extra}h` : '—' }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium text-slate-900">
                                        {{ formatCurrency(row.perceptions) }}
                                    </td>
                                    <td class="px-3 py-3.5 text-right text-rose-600">
                                        {{ formatCurrency(row.isr ?? row.concepts?.isr ?? 0) }}
                                    </td>
                                    <td class="px-3 py-3.5 text-right text-rose-600">
                                        {{ formatCurrency(row.imss ?? row.concepts?.imss ?? 0) }}
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-medium text-rose-700">
                                        {{ formatCurrency(row.deductions) }}
                                    </td>
                                    <td class="px-3 py-3.5 text-right font-semibold text-slate-900">
                                        {{ formatCurrency(row.net) }}
                                    </td>
                                    <td class="px-3 py-3.5 text-center">
                                        <NexusBadge :tone="row.tone">
                                            {{ row.status }}
                                        </NexusBadge>
                                    </td>
                                    <td class="py-3.5 pl-3 pr-6 text-right">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-600 shadow-xs transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                                            @click="openAdjustModal(row)"
                                        >
                                            <SlidersHorizontal class="size-3 text-[#139b9e]" />
                                            Ajustar
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </NexusViewTransition>

        <!-- Modal de Ajuste Manual por Concepto -->
        <NexusPayrollAdjustModal
            :open="adjustModalOpen"
            :employee="selectedEmployee"
            @close="adjustModalOpen = false"
            @save="saveAdjustedEmployee"
        />

        <!-- Modal de Nueva Pre-Nómina -->
        <NexusNewPayrollModal
            :open="newPayrollModalOpen"
            :frequencies="frequencies"
            @close="newPayrollModalOpen = false"
            @create="handleCreatePayrollPeriod"
        />
    </div>
</template>
