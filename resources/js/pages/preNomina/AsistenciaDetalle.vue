<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Save } from '@lucide/vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type AttendanceDetail = {
    id: number;
    date: string;
    employee: string;
    employeeNumber: string;
    checkIn: string | null;
    checkOut: string | null;
    delayMinutes: number;
    overtimeMinutes: number;
    status: 'on_time' | 'late' | 'absent' | 'day_off';
    isManual: boolean;
    scheduleStart: string | null;
    scheduleEnd: string | null;
    isDayOff: boolean;
};

const props = defineProps<{ attendance: AttendanceDetail }>();
const form = useForm({
    check_in: props.attendance.checkIn ?? '',
    check_out: props.attendance.checkOut ?? '',
});
const statusLabels: Record<AttendanceDetail['status'], string> = {
    on_time: 'A tiempo',
    late: 'Retardo',
    absent: 'Falta',
    day_off: 'Descanso',
};

const save = (): void => {
    form.put(`/pre-nomina/asistencias/${props.attendance.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Detalle de asistencia" />
    <NexusPageHeader
        eyebrow="PRE-NÓMINA / CONTROL DE TIEMPO"
        title="Detalle de asistencia"
        description="Consulta y corrige las horas reales del registro seleccionado."
    >
        <template #action>
            <Link
                href="/pre-nomina/asistencias"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                ><ArrowLeft class="size-4" /> Regresar</Link
            >
        </template>
    </NexusPageHeader>

    <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
        <section
            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/30"
        >
            <div
                class="flex items-start justify-between border-b border-slate-100 pb-4"
            >
                <div>
                    <p
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Empleado
                    </p>
                    <h2 class="mt-1 text-lg font-semibold text-slate-900">
                        {{ attendance.employee }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ attendance.employeeNumber }} · {{ attendance.date }}
                    </p>
                </div>
                <span
                    class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600"
                    >{{ statusLabels[attendance.status] }}</span
                >
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg bg-slate-50 p-4">
                    <p
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Horario esperado
                    </p>
                    <p class="mt-2 text-xl font-semibold text-slate-900">
                        {{
                            attendance.isDayOff
                                ? 'Día de descanso'
                                : `${attendance.scheduleStart ?? '—'} — ${attendance.scheduleEnd ?? '—'}`
                        }}
                    </p>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <p
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        Resultado calculado
                    </p>
                    <p class="mt-2 text-sm text-slate-700">
                        Retardo:
                        <strong>{{ attendance.delayMinutes }} min</strong> ·
                        Extra:
                        <strong>{{ attendance.overtimeMinutes }} min</strong>
                    </p>
                </div>
            </div>
            <form class="mt-6" @submit.prevent="save">
                <h3 class="text-sm font-semibold text-slate-900">
                    Corrección manual
                </h3>
                <p class="mt-1 text-xs text-slate-500">
                    Al guardar se recalculan el retardo, las horas extra y el
                    estado del día.
                </p>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <label
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >Entrada real<input
                            v-model="form.check_in"
                            type="time"
                            class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8]"
                        /><span
                            v-if="form.errors.check_in"
                            class="mt-1 block text-xs text-rose-600"
                            >{{ form.errors.check_in }}</span
                        ></label
                    >
                    <label
                        class="text-[10px] font-semibold tracking-wide text-slate-500 uppercase"
                        >Salida real<input
                            v-model="form.check_out"
                            type="time"
                            class="mt-1 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm text-slate-700 outline-none focus:border-[#23c6c8]"
                        /><span
                            v-if="form.errors.check_out"
                            class="mt-1 block text-xs text-rose-600"
                            >{{ form.errors.check_out }}</span
                        ></label
                    >
                </div>
                <div class="mt-5 flex items-center justify-end gap-3">
                    <span
                        v-if="form.recentlySuccessful"
                        class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700"
                        ><Check class="size-4" /> Guardado</span
                    ><button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-[#0a161e] px-3 py-2 text-xs font-semibold text-white hover:bg-[#162732] disabled:opacity-50"
                        :disabled="form.processing"
                    >
                        <Save class="size-3.5" /> Guardar corrección
                    </button>
                </div>
            </form>
        </section>
        <aside
            class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/30"
        >
            <h3 class="text-sm font-semibold text-slate-900">
                Reglas del registro
            </h3>
            <ul class="mt-3 space-y-2 text-xs leading-5 text-slate-600">
                <li>La salida debe ser posterior a la entrada.</li>
                <li>El retardo se compara contra la entrada esperada.</li>
                <li>Las horas extra se comparan contra la salida esperada.</li>
                <li>Esta corrección queda marcada como manual.</li>
            </ul>
        </aside>
    </div>
</template>
