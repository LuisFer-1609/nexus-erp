<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ElOption, ElSelect } from 'element-plus';
import { computed, onMounted, ref } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import NexusPreNominaSkeleton from '@/components/nexus/NexusPreNominaSkeleton.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';

type EmployeeRow = {
    id: number;
    initials: string;
    name: string;
    area: string;
    role: string;
    contact: string;
    date: string;
    status: string;
    schedule: string;
};

const props = withDefaults(
    defineProps<{
        areas?: string[];
        employees?: EmployeeRow[];
    }>(),
    {
        areas: () => [],
        employees: () => [],
    },
);

const isLoading = ref(true);
const searchQuery = ref('');
const selectedArea = ref('Todas');
const filterDirection = ref<'up' | 'down' | 'left' | 'right'>('up');

const filteredEmployees = computed(() =>
    props.employees.filter((emp) => {
        const matchesArea =
            selectedArea.value === 'Todas' || emp.area === selectedArea.value;
        const q = searchQuery.value.trim().toLowerCase();
        const matchesQuery =
            !q ||
            emp.name.toLowerCase().includes(q) ||
            emp.area.toLowerCase().includes(q) ||
            emp.role.toLowerCase().includes(q);
        return matchesArea && matchesQuery;
    }),
);

onMounted(() => {
    window.setTimeout(() => {
        isLoading.value = false;
    }, 320);
});
</script>

<template>
    <div class="space-y-5">
        <Head title="Empleados" />

        <NexusPageHeader
            eyebrow="PRE-NÓMINA / Empleados"
            title="Empleados"
            description="Gestiona el personal registrado y su información laboral."
        />

        <NexusViewTransition :direction="filterDirection" mode="out-in">
            <NexusPreNominaSkeleton
                v-if="isLoading"
                key="emp-skeleton"
                :show-kpis="false"
            />

            <div v-else key="emp-content" class="space-y-5">
                <section
                    class="rounded-[12px] border border-slate-200/90 bg-white p-4"
                >
                    <div class="grid gap-3 md:grid-cols-[1.6fr_1fr]">
                        <input
                            v-model="searchQuery"
                            class="h-10 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs transition outline-none focus:border-[#23c6c8] focus:bg-white"
                            placeholder="Buscar empleado, puesto o área..."
                        />
                        <ElSelect
                            v-model="selectedArea"
                            placeholder="Filtrar por área"
                            class="w-full"
                            @change="filterDirection = 'left'"
                        >
                            <ElOption label="Área: Todas" value="Todas" />
                            <ElOption
                                v-for="area in props.areas"
                                :key="area"
                                :label="area"
                                :value="area"
                            />
                        </ElSelect>
                    </div>
                </section>

                <NexusEmptyState
                    v-if="filteredEmployees.length === 0"
                    description="Aún no hay empleados registrados."
                />

                <section
                    v-else
                    class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
                >
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 class="text-sm font-semibold text-slate-900">
                            Directorio de empleados
                        </h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[900px] text-left">
                            <thead
                                class="bg-slate-50 text-xs font-semibold tracking-[0.05em] text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-5 py-3">Empleado</th>
                                    <th class="px-4 py-3">Área / puesto</th>
                                    <th class="px-4 py-3">Contacto</th>
                                    <th class="px-4 py-3">Ingreso</th>
                                    <th class="px-4 py-3">Estado</th>
                                    <th class="px-5 py-3">Horario</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="employee in filteredEmployees"
                                    :key="employee.id"
                                    class="transition-colors hover:bg-slate-50/80"
                                >
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="flex size-8 items-center justify-center rounded-full bg-cyan-50 text-xs font-semibold text-[#139b9e]"
                                            >
                                                {{ employee.initials }}
                                            </div>
                                            <p
                                                class="text-xs font-semibold text-slate-900"
                                            >
                                                {{ employee.name }}
                                            </p>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-700"
                                    >
                                        {{ employee.area }} ·
                                        {{ employee.role }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-600"
                                    >
                                        {{ employee.contact }}
                                    </td>
                                    <td
                                        class="px-4 py-3.5 text-xs text-slate-500"
                                    >
                                        {{ employee.date }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <NexusBadge
                                            :label="employee.status"
                                            tone="green"
                                        />
                                    </td>
                                    <td
                                        class="px-5 py-3.5 text-xs font-medium text-slate-700"
                                    >
                                        {{ employee.schedule }}
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
