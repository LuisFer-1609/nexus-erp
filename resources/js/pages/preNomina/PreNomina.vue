<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CheckCircle2, Download, RefreshCw } from '@lucide/vue';
import { ElOption, ElSelect } from 'element-plus';
import { computed, onMounted, ref, watch } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
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
        periods?: Array<{
            id: string;
            label: string;
            status: string;
            tone: 'amber' | 'green' | 'cyan' | 'slate';
        }>;
        currentPeriod?: PayrollPeriod | null;
    }>(),
    {
        selectedPeriodId: '',
        periods: () => [],
        currentPeriod: null,
    },
);

const activePeriodId = ref(props.selectedPeriodId);
const isLoading = ref(true);
const isRecalculating = ref(false);
const transitionDirection = ref<'up' | 'down' | 'left' | 'right'>('up');
const feedbackBanner = ref<string | null>(null);
const selectedEmployee = ref<PayrollRow | null>(null);
const adjustModalOpen = ref(false);

const payrollRows = ref<PayrollRow[]>(
    props.currentPeriod?.rows.map((r) => ({ ...r })) ?? [],
);

watch(
    () => props.currentPeriod,
    (nextPeriod) => {
        payrollRows.value = nextPeriod?.rows.map((r) => ({ ...r })) ?? [];
    },
);

const currentPeriodMeta = computed(() =>
    props.periods.find((p) => p.id === activePeriodId.value),
);

const computedSummary = computed<PayrollSummary>(() => {
    const perceptions = payrollRows.value.reduce(
        (sum, r) => sum + r.perceptions,
        0,
    );
    const deductions = payrollRows.value.reduce(
        (sum, r) => sum + r.deductions,
        0,
    );
    const net = Math.max(0, perceptions - deductions);

    return {
        employees: payrollRows.value.length,
        perceptions,
        deductions,
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
    const prevIndex = props.periods.findIndex(
        (p) => p.id === activePeriodId.value,
    );
    const nextIndex = props.periods.findIndex((p) => p.id === periodKey);
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
        showFeedback('Pre-nómina recalculada correctamente.');
    }, 480);
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
    showFeedback('Reporte de pre-nómina exportado correctamente en CSV.');
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
    showFeedback(`Valores ajustados para ${updated.name}.`);
};

onMounted(() => {
    window.setTimeout(() => {
        isLoading.value = false;
    }, 360);
});
</script>

<template>
    <div class="space-y-5">
        <Head title="Pre-nómina" />

        <NexusPageHeader
            eyebrow="Recursos Humanos / Pre-nómina"
            title="Pre-nómina"
            description="Calcula percepciones y deducciones a partir de asistencias registradas."
        />

        <NexusViewTransition direction="down" mode="out-in">
            <div
                v-if="feedbackBanner"
                :key="feedbackBanner"
                class="flex items-center gap-2.5 rounded-[10px] border border-cyan-200 bg-cyan-50/80 px-4 py-2.5 text-xs font-medium text-[#0e7f82]"
            >
                <CheckCircle2 class="size-4 shrink-0 text-[#23c6c8]" />
                <span>{{ feedbackBanner }}</span>
            </div>
        </NexusViewTransition>

        <section
            class="rounded-[12px] border border-slate-200/90 bg-white px-5 py-4"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
            >
                <div>
                    <span
                        class="mb-1.5 block text-xs font-medium text-slate-500"
                    >
                        Período
                    </span>
                    <div class="flex flex-wrap items-center gap-3">
                        <ElSelect
                            v-model="activePeriodId"
                            placeholder="Seleccionar período"
                            class="w-60"
                            :disabled="props.periods.length === 0"
                            @change="handlePeriodChange"
                        >
                            <ElOption
                                v-for="period in props.periods"
                                :key="period.id"
                                :label="period.label"
                                :value="period.id"
                            />
                        </ElSelect>

                        <NexusViewTransition
                            v-if="currentPeriodMeta"
                            direction="right"
                            mode="out-in"
                        >
                            <NexusBadge
                                :key="currentPeriodMeta.status"
                                :label="currentPeriodMeta.status"
                                :tone="currentPeriodMeta.tone"
                            />
                        </NexusViewTransition>
                    </div>
                </div>

                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        :disabled="
                            isRecalculating || payrollRows.length === 0
                        "
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg bg-[#23c6c8] px-5 text-xs font-semibold text-white shadow-xs transition hover:-translate-y-0.5 hover:bg-[#1db2b4] hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transform-none"
                        @click="handleRecalculate"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="isRecalculating ? 'animate-spin' : ''"
                        />
                        <span>Recalcular</span>
                    </button>

                    <button
                        type="button"
                        :disabled="payrollRows.length === 0"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-slate-200 bg-white px-5 text-xs font-semibold text-slate-800 transition hover:-translate-y-0.5 hover:border-slate-300 hover:bg-slate-50 hover:shadow-xs disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transform-none"
                        @click="handleExport"
                    >
                        <Download class="size-3.5 text-slate-500" />
                        <span>Exportar reporte</span>
                    </button>
                </div>
            </div>
        </section>

        <NexusViewTransition :direction="transitionDirection" mode="out-in">
            <NexusPreNominaSkeleton
                v-if="isLoading"
                key="payroll-skeleton"
                :rows="5"
                :show-kpis="true"
            />

            <div
                v-else
                :key="`payroll-content-${activePeriodId}`"
                class="space-y-5"
            >
                <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <NexusKpiCard
                        label="Empleados"
                        :value="computedSummary.employees"
                        helper="Período actual"
                        tone="cyan"
                    />
                    <NexusKpiCard
                        label="Percepciones"
                        :value="formatCurrency(computedSummary.perceptions)"
                        helper="Bruto estimado"
                        tone="green"
                    />
                    <NexusKpiCard
                        label="Deducciones"
                        :value="formatCurrency(computedSummary.deductions)"
                        helper="Incidencias"
                        tone="red"
                    />
                    <NexusKpiCard
                        label="Neto estimado"
                        :value="formatCurrency(computedSummary.net)"
                        helper="Antes de nómina final"
                        tone="violet"
                    />
                </section>

                <NexusEmptyState
                    v-if="payrollRows.length === 0"
                    description="Aún no hay periodos de pre-nómina disponibles."
                />

                <section
                    v-else
                    class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
                >
                    <div class="px-6 pt-5 pb-4">
                        <h2
                            class="text-[15px] font-semibold tracking-tight text-slate-900"
                        >
                            Detalle por empleado
                        </h2>
                        <p class="mt-1 text-xs text-slate-500">
                            Los valores pueden ajustarse antes de exportar.
                        </p>
                    </div>

                    <div class="overflow-x-auto px-3 pb-4">
                        <table
                            class="w-full min-w-[920px] border-separate border-spacing-y-1 text-left"
                        >
                            <thead>
                                <tr
                                    class="rounded-lg bg-[#f8fafc] text-xs font-medium text-slate-500"
                                >
                                    <th
                                        class="rounded-l-lg border-y border-l border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Empleado
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Días
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Ret.
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Faltas
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        H. extra
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Percepciones
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Deducciones
                                    </th>
                                    <th
                                        class="border-y border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Neto
                                    </th>
                                    <th
                                        class="rounded-r-lg border-y border-r border-slate-100 px-4 py-3 font-medium"
                                    >
                                        Estado
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in payrollRows"
                                    :key="row.id"
                                    class="group cursor-pointer rounded-lg transition-colors duration-150 hover:bg-cyan-50/40"
                                    :class="
                                        index % 2 === 1
                                            ? 'bg-[#f8fafc]'
                                            : 'bg-white'
                                    "
                                    title="Clic para ajustar valores del empleado"
                                    @click="openAdjustModal(row)"
                                >
                                    <td
                                        class="rounded-l-lg px-4 py-3.5 text-xs font-semibold text-slate-900 group-hover:text-[#139b9e]"
                                    >
                                        {{ row.name }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ row.days }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ row.delays }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ row.absences }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ row.extra }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ formatCurrency(row.perceptions) }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-500"
                                    >
                                        {{ formatCurrency(row.deductions) }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs font-bold text-slate-900"
                                    >
                                        {{ formatCurrency(row.net) }}
                                    </td>
                                    <td class="rounded-r-lg px-4 py-3.5">
                                        <NexusBadge
                                            :label="row.status"
                                            :tone="row.tone"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </NexusViewTransition>

        <NexusPayrollAdjustModal
            :open="adjustModalOpen"
            :employee="selectedEmployee"
            @close="adjustModalOpen = false"
            @save="saveAdjustedEmployee"
        />
    </div>
</template>
