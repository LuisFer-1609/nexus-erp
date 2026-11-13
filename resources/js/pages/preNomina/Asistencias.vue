<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import NexusPreNominaSkeleton from '@/components/nexus/NexusPreNominaSkeleton.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';

type AttendanceRow = {
    id: number;
    initials: string;
    name: string;
    area: string;
    entry: string;
    exit: string;
    delay: string;
    extra: string;
    status: string;
    tone: 'green' | 'amber' | 'red';
};

const props = withDefaults(
    defineProps<{
        attendances?: AttendanceRow[];
    }>(),
    {
        attendances: () => [],
    },
);

const isLoading = ref(true);

onMounted(() => {
    window.setTimeout(() => {
        isLoading.value = false;
    }, 320);
});
</script>

<template>
    <div class="space-y-5">
        <Head title="Asistencias" />

        <NexusPageHeader
            eyebrow="Recursos Humanos / Asistencias"
            title="Control de asistencias"
            description="Registro diario de entradas, salidas, retardos, faltas y horas extra."
        />

        <NexusViewTransition direction="up" mode="out-in">
            <NexusPreNominaSkeleton
                v-if="isLoading"
                key="att-skeleton"
                :show-kpis="false"
            />

            <div v-else key="att-content" class="space-y-5">
                <NexusEmptyState
                    v-if="props.attendances.length === 0"
                    description="Aún no hay registros de asistencia."
                />

                <section
                    v-else
                    class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-100 px-5 py-4"
                    >
                        <h2 class="text-sm font-semibold text-slate-900">
                            Resumen de asistencias vinculadas a Pre-nómina
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead
                                class="bg-slate-50 text-xs font-semibold tracking-[0.05em] text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-5 py-3">Empleado</th>
                                    <th class="px-4 py-3">Área</th>
                                    <th class="px-4 py-3">Entrada</th>
                                    <th class="px-4 py-3">Salida</th>
                                    <th class="px-4 py-3">Retardos</th>
                                    <th class="px-4 py-3">H. extra</th>
                                    <th class="px-5 py-3">Estado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="row in props.attendances"
                                    :key="row.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-xs font-semibold text-[#139b9e]"
                                            >
                                                {{ row.initials }}
                                            </div>
                                            <p
                                                class="text-xs font-semibold text-slate-900"
                                            >
                                                {{ row.name }}
                                            </p>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-700"
                                    >
                                        {{ row.area }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-700"
                                    >
                                        {{ row.entry }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-700"
                                    >
                                        {{ row.exit }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs"
                                        :class="
                                            row.delay !== '—'
                                                ? 'font-medium text-amber-600'
                                                : 'text-slate-400'
                                        "
                                    >
                                        {{ row.delay }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ row.extra }}
                                    </td>
                                    <td class="px-5 py-3.5">
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
    </div>
</template>
