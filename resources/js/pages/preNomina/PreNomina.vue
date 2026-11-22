<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Building2,
    Calendar,
    Check,
    CheckCircle2,
    ChevronDown,
    ChevronRight,
    Clock,
    DollarSign,
    Download,
    FileSpreadsheet,
    FileText,
    Layers,
    Lock,
    PlusCircle,
    RefreshCw,
    Search,
    ShieldAlert,
    ShieldCheck,
    SlidersHorizontal,
    Sparkles,
    Trash2,
    TrendingDown,
    TrendingUp,
    UserCheck,
    Users,
    X,
} from '@lucide/vue';
import { ElDropdown, ElDropdownItem, ElDropdownMenu, ElOption, ElSelect, ElTooltip } from 'element-plus';
import { computed, onMounted, ref, watch } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusNewPayrollModal from '@/components/nexus/NexusNewPayrollModal.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import NexusPayrollAdjustModal from '@/components/nexus/NexusPayrollAdjustModal.vue';
import NexusPreNominaSkeleton from '@/components/nexus/NexusPreNominaSkeleton.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';
import {
    exportBankDispersionLayout,
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
            { id: '1', name: 'Semanal' },
            { id: '2', name: 'Quincenal' },
            { id: '3', name: 'Mensual' },
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

// Estado de aprobación del período (Stepper)
const currentStage = ref<'Borrador' | 'En Revisión' | 'Aprobado RH' | 'Cerrado'>('En Revisión');

// Filas expandidas (Accordion interactivo)
const expandedRows = ref<Set<number>>(new Set());

// Selección múltiple para acciones masivas (Bulk actions)
const selectedRowIds = ref<Set<number>>(new Set());

const localPeriods = ref([...props.periods]);
const payrollRows = ref<PayrollRow[]>(
    props.currentPeriod?.rows.map((r) => ({ ...r })) ?? [],
);

watch(
    () => props.currentPeriod,
    (nextPeriod) => {
        payrollRows.value = nextPeriod?.rows.map((r) => ({ ...r })) ?? [];
        selectedRowIds.value.clear();
        expandedRows.value.clear();
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
            p.frequency?.toLowerCase() === filterFrequency.value.toLowerCase(),
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

const isAllSelected = computed(
    () =>
        displayedRows.value.length > 0 &&
        displayedRows.value.every((r) => selectedRowIds.value.has(r.id)),
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

// Porcentajes para barra apilada de distribución fiscal
const distributionPercentages = computed(() => {
    const total = computedSummary.value.perceptions;
    if (total <= 0) return { net: 100, isr: 0, imss: 0, other: 0 };
    const netPct = Math.round((computedSummary.value.net / total) * 100);
    const isrPct = Math.round(((computedSummary.value.isr ?? 0) / total) * 100);
    const imssPct = Math.round(((computedSummary.value.imss ?? 0) / total) * 100);
    const otherPct = Math.max(0, 100 - netPct - isrPct - imssPct);
    return { net: netPct, isr: isrPct, imss: imssPct, other: otherPct };
});

// Detección de discrepancias inteligente
const discrepancies = computed(() => {
    const issues: string[] = [];
    payrollRows.value.forEach((r) => {
        if (r.net <= 0) {
            issues.push(`${r.name} tiene un sueldo neto de $0.00`);
        }
        if (r.deductions > r.perceptions * 0.4) {
            issues.push(`${r.name} excede el 40% de deducciones sobre el salario`);
        }
    });
    return issues;
});

const showFeedback = (message: string): void => {
    feedbackBanner.value = message;
    window.setTimeout(() => {
        if (feedbackBanner.value === message) {
            feedbackBanner.value = null;
        }
    }, 3600);
};

const toggleRowExpansion = (id: number): void => {
    if (expandedRows.value.has(id)) {
        expandedRows.value.delete(id);
    } else {
        expandedRows.value.add(id);
    }
};

const toggleSelectAll = (): void => {
    if (isAllSelected.value) {
        selectedRowIds.value.clear();
    } else {
        displayedRows.value.forEach((r) => selectedRowIds.value.add(r.id));
    }
};

const toggleSelectRow = (id: number): void => {
    if (selectedRowIds.value.has(id)) {
        selectedRowIds.value.delete(id);
    } else {
        selectedRowIds.value.add(id);
    }
};

const handleBulkApprove = (): void => {
    payrollRows.value = payrollRows.value.map((r) => {
        if (selectedRowIds.value.has(r.id)) {
            return { ...r, status: 'Correcto', tone: 'green' };
        }
        return r;
    });
    showFeedback(`${selectedRowIds.value.size} colaboradores aprobados masivamente.`);
    selectedRowIds.value.clear();
};

const handleBulkBonus = (): void => {
    const bonusAmount = 500;
    payrollRows.value = payrollRows.value.map((r) => {
        if (selectedRowIds.value.has(r.id)) {
            return recalculateEmployeeRow(r, { bonus: (r.concepts?.bonus ?? 0) + bonusAmount });
        }
        return r;
    });
    showFeedback(`Se aplicó bono de $500 MXN a ${selectedRowIds.value.size} colaboradores.`);
    selectedRowIds.value.clear();
};

const handlePeriodChange = (nextId: string | number | boolean): void => {
    const periodKey = String(nextId);
    const prevIndex = localPeriods.value.findIndex((p) => p.id === activePeriodId.value);
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
        payrollRows.value = payrollRows.value.map((row) => recalculateEmployeeRow(row));
        isLoading.value = false;
        isRecalculating.value = false;
        showFeedback('Pre-nómina recalculada con éxito desde asistencias.');
    }, 450);
};

const handleExportCsv = (): void => {
    if (payrollRows.value.length === 0) return;
    exportPayrollToCsv(currentPeriodMeta.value?.label ?? 'Periodo', payrollRows.value, computedSummary.value);
    showFeedback('Reporte detallado en CSV descargado.');
};

const handleExportBank = (bank: 'BBVA' | 'Banorte' | 'Santander'): void => {
    if (payrollRows.value.length === 0) return;
    exportBankDispersionLayout(bank, currentPeriodMeta.value?.code ?? 'NOM', payrollRows.value);
    showFeedback(`Layout bancario de dispersión para ${bank} generado exitosamente.`);
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
        code: `NOM-${Math.floor(250 + Math.random() * 50)}`,
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
    currentStage.value = 'Borrador';
    showFeedback(`Pre-Nómina "${payload.title}" creada y calculada exitosamente.`);
};

onMounted(() => {
    window.setTimeout(() => {
        isLoading.value = false;
    }, 300);
});
</script>

<template>
    <Head title="Pre-nómina — NexusERP 2.0" />

    <div class="space-y-6 pb-20">
        <!-- Toast feedback banner -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="-translate-y-3 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="feedbackBanner"
                class="flex items-center gap-2.5 rounded-2xl border border-emerald-200 bg-emerald-50/95 px-4 py-3 text-xs font-medium text-emerald-900 shadow-md backdrop-blur-sm"
                role="status"
            >
                <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                <span>{{ feedbackBanner }}</span>
            </div>
        </Transition>

        <!-- Page Header con Badge de IA y Estatus -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <NexusPageHeader
                category="Recursos Humanos / Nómina"
                title="Pre-nómina"
                description="Cálculo integral de percepciones, deducciones de ley (ISR, IMSS) e incidencias bancarias."
            />

            <!-- Stepper de Estados de Aprobación de Nómina -->
            <div class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white p-1.5 shadow-xs">
                <button
                    type="button"
                    :class="currentStage === 'Borrador' ? 'bg-amber-500 text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                    class="rounded-xl px-3 py-1.5 text-xs transition"
                    @click="currentStage = 'Borrador'"
                >
                    1. Borrador
                </button>
                <ChevronRight class="size-3 text-slate-300" />
                <button
                    type="button"
                    :class="currentStage === 'En Revisión' ? 'bg-[#23c6c8] text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                    class="rounded-xl px-3 py-1.5 text-xs transition"
                    @click="currentStage = 'En Revisión'"
                >
                    2. En Revisión
                </button>
                <ChevronRight class="size-3 text-slate-300" />
                <button
                    type="button"
                    :class="currentStage === 'Aprobado RH' ? 'bg-emerald-600 text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                    class="rounded-xl px-3 py-1.5 text-xs transition"
                    @click="currentStage = 'Aprobado RH'"
                >
                    3. Aprobado RH
                </button>
                <ChevronRight class="size-3 text-slate-300" />
                <button
                    type="button"
                    :class="currentStage === 'Cerrado' ? 'bg-slate-900 text-white shadow-xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                    class="rounded-xl px-3 py-1.5 text-xs transition flex items-center gap-1"
                    @click="currentStage = 'Cerrado'"
                >
                    <Lock class="size-3" />
                    4. Cerrado
                </button>
            </div>
        </div>

        <!-- Alerta de Auditoría Inteligente / Discrepancias (Si existen) -->
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0 translate-y-1">
            <div
                v-if="discrepancies.length > 0"
                class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900"
            >
                <ShieldAlert class="size-4 shrink-0 text-amber-600 mt-0.5" />
                <div class="flex-1">
                    <p class="font-semibold text-amber-950">Auditoría Automática de Pre-Nómina:</p>
                    <ul class="mt-1 list-disc list-inside space-y-0.5 text-amber-800 text-[11px]">
                        <li v-for="(issue, i) in discrepancies" :key="i">{{ issue }}</li>
                    </ul>
                </div>
            </div>
        </Transition>

        <!-- Top Controls Bar -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex flex-wrap items-center gap-3">
                <!-- Selector de Frecuencia con Element Plus -->
                <div class="w-48">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Periodicidad
                    </label>
                    <ElSelect v-model="filterFrequency" class="w-full" size="default">
                        <ElOption
                            v-for="freq in frequencies"
                            :key="freq.id"
                            :label="freq.name"
                            :value="freq.name"
                        />
                    </ElSelect>
                </div>

                <!-- Selector de Periodo con Element Plus -->
                <div class="w-72">
                    <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        Período de Nómina
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

            <!-- Acciones Principales -->
            <div class="flex flex-wrap items-center gap-2 pt-2 sm:pt-0">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-slate-800"
                    @click="newPayrollModalOpen = true"
                >
                    <PlusCircle class="size-3.5 text-[#23c6c8]" />
                    Nueva Pre-Nómina
                </button>

                <button
                    type="button"
                    :disabled="isRecalculating || currentStage === 'Cerrado'"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#23c6c8] px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-[#1eb0b2] disabled:opacity-50"
                    @click="handleRecalculate"
                >
                    <RefreshCw
                        class="size-3.5"
                        :class="{ 'animate-spin': isRecalculating }"
                    />
                    Recalcular
                </button>

                <!-- Menú Desplegable de Exportaciones Avanzadas (CSV y Layouts Bancarios) -->
                <ElDropdown trigger="click">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-medium text-slate-700 shadow-xs transition hover:bg-slate-50"
                    >
                        <Download class="size-3.5 text-slate-500" />
                        Exportar
                        <ChevronDown class="size-3 text-slate-400" />
                    </button>
                    <template #dropdown>
                        <ElDropdownMenu>
                            <ElDropdownItem @click="handleExportCsv">
                                <FileSpreadsheet class="size-4 mr-2 text-emerald-600" />
                                Reporte Detallado CSV
                            </ElDropdownItem>
                            <ElDropdownItem divided @click="handleExportBank('BBVA')">
                                <Building2 class="size-4 mr-2 text-blue-600" />
                                Layout Dispersión BBVA (.txt)
                            </ElDropdownItem>
                            <ElDropdownItem @click="handleExportBank('Banorte')">
                                <Building2 class="size-4 mr-2 text-red-600" />
                                Layout Dispersión Banorte (.txt)
                            </ElDropdownItem>
                            <ElDropdownItem @click="handleExportBank('Santander')">
                                <Building2 class="size-4 mr-2 text-rose-600" />
                                Layout Dispersión Santander (.txt)
                            </ElDropdownItem>
                        </ElDropdownMenu>
                    </template>
                </ElDropdown>
            </div>
        </div>

        <!-- Skeleton loader -->
        <NexusPreNominaSkeleton v-if="isLoading" />

        <!-- Main Content -->
        <NexusViewTransition v-else :direction="transitionDirection" mode="out-in">
            <div :key="activePeriodId" class="space-y-6">
                <!-- KPI Summary Cards con Barra de Distribución Apilada -->
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Colaboradores</span>
                                <Users class="size-4 text-cyan-600" />
                            </div>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ computedSummary.employees }}</p>
                        </div>
                        <p class="mt-2 text-[11px] text-cyan-700 font-medium">En ciclo activo de pago</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Percepciones Brutas</span>
                                <TrendingUp class="size-4 text-emerald-600" />
                            </div>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(computedSummary.perceptions) }}</p>
                        </div>
                        <p class="mt-2 text-[11px] text-emerald-700 font-medium">Sueldos, horas extra y bonos</p>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Deducciones e Impuestos</span>
                                <TrendingDown class="size-4 text-rose-600" />
                            </div>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(computedSummary.deductions) }}</p>
                        </div>
                        <p class="mt-2 text-[11px] text-rose-700 font-medium">
                            ISR: {{ formatCurrency(computedSummary.isr ?? 0) }} • IMSS: {{ formatCurrency(computedSummary.imss ?? 0) }}
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-slate-500">
                                <span>Neto Estimado</span>
                                <DollarSign class="size-4 text-purple-600" />
                            </div>
                            <p class="mt-2 text-2xl font-bold text-slate-900">{{ formatCurrency(computedSummary.net) }}</p>
                        </div>
                        <!-- Mini Barra Apilada Visual -->
                        <div class="mt-2 space-y-1">
                            <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div :style="{ width: `${distributionPercentages.net}%` }" class="bg-[#23c6c8]" title="Neto"></div>
                                <div :style="{ width: `${distributionPercentages.isr}%` }" class="bg-rose-500" title="ISR"></div>
                                <div :style="{ width: `${distributionPercentages.imss}%` }" class="bg-amber-500" title="IMSS"></div>
                            </div>
                            <div class="flex justify-between text-[10px] text-slate-400">
                                <span>{{ distributionPercentages.net }}% Neto</span>
                                <span>{{ distributionPercentages.isr }}% ISR</span>
                                <span>{{ distributionPercentages.imss }}% IMSS</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detalle por Empleado Table con Acordeón y Checkboxes -->
                <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
                    <!-- Table Header & Controls -->
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">
                                Detalle por colaborador y conceptos
                            </h2>
                            <p class="text-xs text-slate-500">
                                Haz clic en cualquier colaborador para ver su desglose interactivo o utiliza los checkboxes para acciones masivas.
                            </p>
                        </div>

                        <!-- Filtro por nombre y estado -->
                        <div class="flex items-center gap-3">
                            <div class="relative">
                                <Search class="absolute left-2.5 top-2 size-3.5 text-slate-400" />
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Buscar por colaborador..."
                                    class="rounded-xl border border-slate-200 pl-8 pr-3 py-1.5 text-xs text-slate-800 placeholder-slate-400 focus:border-[#23c6c8] focus:outline-none"
                                />
                            </div>
                            <ElSelect v-model="filterStatus" size="small" class="w-32">
                                <ElOption label="Todos" value="todas" />
                                <ElOption label="Correcto" value="Correcto" />
                                <ElOption label="Revisar" value="Revisar" />
                            </ElSelect>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-if="displayedRows.length === 0" class="p-8">
                        <NexusEmptyState
                            title="No hay empleados en este período"
                            description="Intenta seleccionar otra periodicidad o agrega una nueva pre-nómina."
                        />
                    </div>

                    <!-- Table -->
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-semibold text-slate-500">
                                <tr>
                                    <th class="py-3.5 pl-4 pr-2 text-center w-8">
                                        <input
                                            type="checkbox"
                                            :checked="isAllSelected"
                                            class="rounded border-slate-300 text-[#23c6c8] focus:ring-[#23c6c8]"
                                            @change="toggleSelectAll"
                                        />
                                    </th>
                                    <th class="py-3.5 pl-2 pr-3">Colaborador</th>
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
                                <template v-for="row in displayedRows" :key="row.id">
                                    <tr
                                        class="cursor-pointer transition hover:bg-slate-50/70"
                                        :class="{ 'bg-cyan-50/30': expandedRows.has(row.id) }"
                                        @click="toggleRowExpansion(row.id)"
                                    >
                                        <td class="py-3.5 pl-4 pr-2 text-center" @click.stop>
                                            <input
                                                type="checkbox"
                                                :checked="selectedRowIds.has(row.id)"
                                                class="rounded border-slate-300 text-[#23c6c8] focus:ring-[#23c6c8]"
                                                @change="toggleSelectRow(row.id)"
                                            />
                                        </td>
                                        <td class="py-3.5 pl-2 pr-3">
                                            <div class="flex items-center gap-3">
                                                <button type="button" class="text-slate-400 hover:text-slate-700">
                                                    <ChevronDown v-if="expandedRows.has(row.id)" class="size-3.5" />
                                                    <ChevronRight v-else class="size-3.5" />
                                                </button>
                                                <div
                                                    class="flex size-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-700"
                                                >
                                                    {{ row.initials ?? 'EM' }}
                                                </div>
                                                <div>
                                                    <p class="font-medium text-slate-900">{{ row.name }}</p>
                                                    <p class="text-[10px] text-slate-400">{{ row.area ?? 'General' }}</p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-3.5 text-center font-medium text-slate-700">{{ row.days }}</td>
                                        <td class="px-2 py-3.5 text-center">
                                            <span :class="row.delays > 0 ? 'font-semibold text-amber-600' : 'text-slate-400'">
                                                {{ row.delays }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3.5 text-center">
                                            <span :class="row.absences > 0 ? 'font-semibold text-rose-600' : 'text-slate-400'">
                                                {{ row.absences }}
                                            </span>
                                        </td>
                                        <td class="px-2 py-3.5 text-center">
                                            <span :class="row.extra > 0 ? 'font-semibold text-cyan-700' : 'text-slate-400'">
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
                                        <td class="px-3 py-3.5 text-right font-bold text-slate-900">
                                            {{ formatCurrency(row.net) }}
                                        </td>
                                        <td class="px-3 py-3.5 text-center">
                                            <ElTooltip :content="row.status === 'Revisar' ? 'Tiene retardos o faltas que requieren validación' : 'Asistencias y montos correctos'" placement="top">
                                                <NexusBadge :tone="row.tone">
                                                    {{ row.status }}
                                                </NexusBadge>
                                            </ElTooltip>
                                        </td>
                                        <td class="py-3.5 pl-3 pr-6 text-right" @click.stop>
                                            <button
                                                type="button"
                                                :disabled="currentStage === 'Cerrado'"
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 shadow-xs transition hover:bg-slate-50 disabled:opacity-40"
                                                @click="openAdjustModal(row)"
                                            >
                                                <SlidersHorizontal class="size-3 text-[#139b9e]" />
                                                Ajustar
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Fila Expandible con Detalle Interactivo (Accordion) -->
                                    <tr v-if="expandedRows.has(row.id)" class="bg-slate-50/80 border-t border-slate-100">
                                        <td colspan="13" class="px-8 py-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                                                <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-1.5 shadow-2xs">
                                                    <p class="font-semibold text-slate-800 flex items-center gap-1.5 text-[11px]">
                                                        <TrendingUp class="size-3 text-emerald-600" />
                                                        Desglose de Percepciones
                                                    </p>
                                                    <div class="space-y-1 text-slate-600 text-[11px]">
                                                        <div class="flex justify-between">
                                                            <span>Sueldo Base ({{ row.days }} días):</span>
                                                            <span class="font-medium text-slate-900">{{ formatCurrency(row.concepts?.salary ?? (row.days * row.dailyRate)) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>Horas Extra ({{ row.extra }}h):</span>
                                                            <span class="font-medium text-slate-900">+{{ formatCurrency(row.concepts?.overtime ?? (row.extra * row.hourlyOvertimeRate)) }}</span>
                                                        </div>
                                                        <div v-if="(row.concepts?.bonus ?? 0) > 0" class="flex justify-between text-emerald-700 font-medium">
                                                            <span>Bono de Desempeño:</span>
                                                            <span>+{{ formatCurrency(row.concepts?.bonus ?? 0) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-1.5 shadow-2xs">
                                                    <p class="font-semibold text-slate-800 flex items-center gap-1.5 text-[11px]">
                                                        <TrendingDown class="size-3 text-rose-600" />
                                                        Deducciones e Incidencias
                                                    </p>
                                                    <div class="space-y-1 text-slate-600 text-[11px]">
                                                        <div class="flex justify-between">
                                                            <span>Retención ISR:</span>
                                                            <span class="text-rose-600">-{{ formatCurrency(row.isr ?? row.concepts?.isr ?? 0) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>IMSS Trabajador:</span>
                                                            <span class="text-rose-600">-{{ formatCurrency(row.imss ?? row.concepts?.imss ?? 0) }}</span>
                                                        </div>
                                                        <div v-if="row.delays > 0" class="flex justify-between text-amber-700">
                                                            <span>Retardos ({{ row.delays }}):</span>
                                                            <span>-{{ formatCurrency(row.delays * 50) }}</span>
                                                        </div>
                                                        <div v-if="row.absences > 0" class="flex justify-between text-rose-700">
                                                            <span>Faltas injustificadas ({{ row.absences }}):</span>
                                                            <span>-{{ formatCurrency(row.concepts?.absencesPenalty ?? 0) }}</span>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="rounded-xl border border-slate-200 bg-white p-3.5 space-y-1.5 shadow-2xs">
                                                    <p class="font-semibold text-slate-800 flex items-center gap-1.5 text-[11px]">
                                                        <Building2 class="size-3 text-cyan-600" />
                                                        Datos Bancarios y Salario Diario
                                                    </p>
                                                    <div class="space-y-1 text-slate-600 text-[11px]">
                                                        <div class="flex justify-between">
                                                            <span>Salario Diario Integrado:</span>
                                                            <span class="font-medium text-slate-900">{{ formatCurrency(row.dailyRate) }}</span>
                                                        </div>
                                                        <div class="flex justify-between">
                                                            <span>Tarifa Hora Extra:</span>
                                                            <span class="font-medium text-slate-900">{{ formatCurrency(row.hourlyOvertimeRate) }}</span>
                                                        </div>
                                                        <div class="flex justify-between text-slate-500">
                                                            <span>Dispersión:</span>
                                                            <span class="font-medium text-slate-800">Transferencia Electrónica</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </NexusViewTransition>

        <!-- Barra Flotante de Acciones Masivas (Aparece al seleccionar 1 o más filas) -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-12 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-12 opacity-0"
        >
            <div
                v-if="selectedRowIds.size > 0"
                class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-4 rounded-2xl border border-slate-800 bg-slate-950 px-5 py-3 text-xs text-white shadow-2xl backdrop-blur-md"
            >
                <div class="flex items-center gap-2 pr-2 border-r border-slate-700">
                    <span class="flex size-5 items-center justify-center rounded-full bg-[#23c6c8] text-[10px] font-bold text-slate-950">
                        {{ selectedRowIds.size }}
                    </span>
                    <span class="font-medium">Seleccionados</span>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500 transition"
                        @click="handleBulkApprove"
                    >
                        <Check class="size-3.5" />
                        Aprobar Estado
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-500 transition"
                        @click="handleBulkBonus"
                    >
                        <Sparkles class="size-3.5" />
                        Bono Masivo (+$500)
                    </button>
                    <button
                        type="button"
                        class="text-slate-400 hover:text-white p-1 rounded-lg transition"
                        @click="selectedRowIds.clear()"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </Transition>

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
