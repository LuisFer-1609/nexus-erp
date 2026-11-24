<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * Estado de "trabajando" mientras Nexus responde. Muestra los pasos que realmente ocurren
 * (consultar datos, analizar, generar el documento) para que la espera se entienda.
 *
 * Los pasos son una estimación según la pregunta: el servidor responde de una sola vez y no
 * envía progreso. Por eso el texto avanza con el tiempo y se queda en el último paso si tarda.
 */
const props = defineProps<{ request: string }>();

type Plan = { label: string; detail: string }[];

const PLANS = {
    report: [
        {
            label: 'Entendiendo tu solicitud',
            detail: 'Identifico el periodo y los datos que necesitas.',
        },
        {
            label: 'Consultando los datos de tu empresa',
            detail: 'Leo solo la información de tu empresa.',
        },
        {
            label: 'Armando tablas y gráficas',
            detail: 'Calculo totales y comparativos.',
        },
        {
            label: 'Generando el documento PDF',
            detail: 'Doy formato y preparo la descarga.',
        },
    ],
    payroll: [
        {
            label: 'Revisando empleados y periodo',
            detail: 'Verifico la plantilla y las fechas de pago.',
        },
        {
            label: 'Calculando percepciones y deducciones',
            detail: 'Aplico los conceptos de cada empleado.',
        },
        {
            label: 'Preparando el resumen de pre-nómina',
            detail: 'Ordeno los totales para revisión.',
        },
    ],
    data: [
        {
            label: 'Entendiendo tu pregunta',
            detail: 'Identifico qué información necesitas.',
        },
        {
            label: 'Consultando los datos de tu empresa',
            detail: 'Leo solo la información de tu empresa.',
        },
        {
            label: 'Analizando los resultados',
            detail: 'Comparo y resumo lo que encontré.',
        },
        {
            label: 'Redactando la respuesta',
            detail: 'Te lo explico en lenguaje claro.',
        },
    ],
} satisfies Record<string, Plan>;

const plan = computed<Plan>(() => {
    const text = props.request
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

    if (
        /nomina|nómina|prenomina|pre-nomina|empleado|percepcion|deduccion/.test(
            text,
        )
    ) {
        return PLANS.payroll;
    }

    if (/reporte|pdf|grafica|gráfica|documento|exporta|descarga/.test(text)) {
        return PLANS.report;
    }

    return PLANS.data;
});

const STEP_MS = 2400;
const step = ref(0);
const seconds = ref(0);
let stepTimer: ReturnType<typeof setInterval> | undefined;
let clock: ReturnType<typeof setInterval> | undefined;

const current = computed(
    () => plan.value[Math.min(step.value, plan.value.length - 1)],
);
const slow = computed(() => seconds.value >= 12);
const progress = computed(() =>
    Math.min(96, 8 + (step.value + 1) * (88 / plan.value.length)),
);

const reset = (): void => {
    step.value = 0;
    seconds.value = 0;
};

onMounted(() => {
    stepTimer = setInterval(() => {
        if (step.value < plan.value.length - 1) {
            step.value += 1;
        }
    }, STEP_MS);

    clock = setInterval(() => {
        seconds.value += 1;
    }, 1000);
});

watch(
    () => props.request,
    () => reset(),
);

onBeforeUnmount(() => {
    clearInterval(stepTimer);
    clearInterval(clock);
});
</script>

<template>
    <div
        class="nexus-message w-full max-w-md rounded-2xl rounded-tl-md border border-slate-200/80 bg-white px-4 py-3.5 shadow-sm"
    >
        <div class="flex items-center justify-between gap-3">
            <p
                class="flex min-w-0 items-center gap-2 text-sm font-medium text-slate-700"
                role="status"
                aria-live="polite"
            >
                <span class="sr-only">Nexus está trabajando: </span>
                <span
                    :key="current.label"
                    class="nexus-step truncate motion-reduce:animate-none"
                >
                    {{ current.label }}
                </span>
            </p>
            <span
                class="shrink-0 text-xs text-slate-400 tabular-nums"
                aria-hidden="true"
            >
                {{ seconds }}s
            </span>
        </div>

        <div
            class="relative mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100"
            role="progressbar"
            aria-label="Progreso de la respuesta"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="Math.round(progress)"
        >
            <span
                class="absolute inset-y-0 left-0 rounded-full bg-gradient-to-r from-[#139b9e] to-[#23c6c8] transition-[width] duration-700 ease-out motion-reduce:transition-none"
                :style="{ width: `${progress}%` }"
            />
            <span
                class="nexus-shimmer absolute inset-y-0 w-1/3 bg-gradient-to-r from-transparent via-white/70 to-transparent motion-reduce:hidden"
                aria-hidden="true"
            />
        </div>

        <ol class="mt-3 space-y-1.5" aria-hidden="true">
            <li
                v-for="(item, index) in plan"
                :key="item.label"
                class="flex items-center gap-2 text-xs transition-colors duration-500"
                :class="
                    index < step
                        ? 'text-slate-400'
                        : index === step
                          ? 'font-medium text-[#139b9e]'
                          : 'text-slate-300'
                "
            >
                <span
                    class="flex size-4 shrink-0 items-center justify-center rounded-full"
                    :class="
                        index < step
                            ? 'bg-emerald-100 text-emerald-600'
                            : index === step
                              ? 'bg-cyan-100'
                              : 'bg-slate-100'
                    "
                >
                    <span
                        v-if="index < step"
                        class="text-[9px] leading-none font-bold"
                        >✓</span
                    >
                    <span
                        v-else-if="index === step"
                        class="nexus-wave size-1.5 rounded-full bg-[#23c6c8]"
                    />
                </span>
                <span class="truncate">{{ item.label }}</span>
            </li>
        </ol>

        <p
            v-if="slow"
            class="nexus-message mt-3 text-xs leading-5 text-slate-500"
        >
            Sigue trabajando. Las consultas grandes pueden tardar un poco más.
        </p>
    </div>
</template>

<style scoped>
.nexus-step {
    animation: step-in 0.45s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.nexus-shimmer {
    animation: shimmer 1.6s ease-in-out infinite;
}

@keyframes step-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes shimmer {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(320%);
    }
}

@media (prefers-reduced-motion: reduce) {
    .nexus-step,
    .nexus-shimmer {
        animation: none;
    }
}
</style>
