<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { UserPlus, X } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';
import {
    store as storeEmpleado,
    update as updateEmpleado,
} from '@/routes/pre-nomina/empleados';

export type EmployeeCatalogs = {
    areas: Array<{ id: number; code: string; name: string }>;
    positions: Array<{
        id: number;
        area_id: number | null;
        code: string;
        name: string;
    }>;
    nationalities: Array<{
        id: number;
        nationality: string;
        c_pais: string | null;
        country: string | null;
    }>;
    frequencies: Array<{
        id: number;
        code: string;
        name: string;
        days: number;
    }>;
    roles: Array<{ id: number; name: string }>;
};

export type EditableEmployee = {
    id: number;
    code: string;
    first_name: string;
    last_name: string;
    phone: string;
    sex: string;
    curp: string;
    ssn: string;
    hire_date: string;
    nationality_id: number;
    area_id: number;
    position_id: number;
    payroll_frequency_id: number;
    role_id: number;
    raw_status: string;
};

const props = defineProps<{
    open: boolean;
    employee: EditableEmployee | null;
    catalogs: EmployeeCatalogs;
}>();

const emit = defineEmits<{
    close: [];
}>();

const defaultNationalityId = (): number | '' => {
    const mex = props.catalogs.nationalities.find(
        (n) => n.c_pais === 'MEX' || n.nationality.toLowerCase() === 'mexicana',
    );

    return mex?.id ?? props.catalogs.nationalities[0]?.id ?? '';
};

const defaultFrequencyId = (): number | '' => {
    const quincenal = props.catalogs.frequencies.find(
        (f) => f.code === 'quincenal',
    );

    return quincenal?.id ?? props.catalogs.frequencies[0]?.id ?? '';
};

const defaultRoleId = (): number => {
    const general = props.catalogs.roles.find(
        (r) => r.name.toLowerCase() === 'general',
    );

    return general?.id ?? props.catalogs.roles[0]?.id ?? 4;
};

const form = useForm({
    first_name: '',
    last_name: '',
    phone: '',
    code: '',
    sex: '',
    curp: '',
    ssn: '',
    hire_date: new Date().toISOString().slice(0, 10),
    nationality_id: '' as number | '',
    area_id: '' as number | '',
    position_id: '' as number | '',
    payroll_frequency_id: '' as number | '',
    role_id: 4,
    status: 'activo',
});

const availablePositions = computed(() => {
    if (!form.area_id) {
        return props.catalogs.positions;
    }

    const areaMatches = props.catalogs.positions.filter(
        (p) => p.area_id === Number(form.area_id) || p.area_id === null,
    );

    return areaMatches.length > 0 ? areaMatches : props.catalogs.positions;
});

watch(
    () => [props.open, props.employee] as const,
    ([isOpen, emp]) => {
        if (!isOpen) {
            return;
        }
        form.clearErrors();
        if (emp) {
            form.first_name = emp.first_name;
            form.last_name = emp.last_name;
            form.phone = emp.phone;
            form.code = emp.code;
            form.sex = emp.sex;
            form.curp = emp.curp;
            form.ssn = emp.ssn;
            form.hire_date =
                emp.hire_date || new Date().toISOString().slice(0, 10);
            form.nationality_id = emp.nationality_id;
            form.area_id = emp.area_id;
            form.position_id = emp.position_id;
            form.payroll_frequency_id = emp.payroll_frequency_id;
            form.role_id = emp.role_id || defaultRoleId();
            form.status = emp.raw_status || 'activo';
        } else {
            form.first_name = '';
            form.last_name = '';
            form.phone = '';
            form.code = '';
            form.sex = '';
            form.curp = '';
            form.ssn = '';
            form.hire_date = new Date().toISOString().slice(0, 10);
            form.nationality_id = defaultNationalityId();
            form.area_id = props.catalogs.areas[0]?.id ?? '';
            const firstPos = props.catalogs.positions.find(
                (p) => p.area_id === Number(form.area_id) || p.area_id === null,
            );
            form.position_id =
                firstPos?.id ?? props.catalogs.positions[0]?.id ?? '';
            form.payroll_frequency_id = defaultFrequencyId();
            form.role_id = defaultRoleId();
            form.status = 'activo';
        }
    },
);

const handleAreaChange = (): void => {
    if (
        !availablePositions.value.some((p) => p.id === Number(form.position_id))
    ) {
        form.position_id = availablePositions.value[0]?.id ?? '';
    }
};

const submit = (): void => {
    const payload = {
        ...form.data(),
        curp: form.curp.trim().toUpperCase(),
        code: form.code.trim().toUpperCase(),
    };

    form.transform(() => payload);

    if (props.employee) {
        form.put(updateEmpleado.url(props.employee.id), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    } else {
        form.post(storeEmpleado.url(), {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        });
    }
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
                        role="dialog"
                        aria-modal="true"
                        class="flex h-full w-full max-w-xl flex-col border-l border-slate-200 bg-white shadow-2xl"
                    >
                        <div
                            class="flex items-center justify-between border-b border-slate-100 px-6 py-5"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex size-9 items-center justify-center rounded-lg bg-cyan-50 text-[#139b9e]"
                                >
                                    <UserPlus class="size-4" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{
                                            employee
                                                ? 'Editar empleado'
                                                : 'Nuevo empleado'
                                        }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        Datos personales y asignación laboral
                                        desde catálogos
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                                aria-label="Cerrar"
                                @click="emit('close')"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <form
                            class="flex min-h-0 flex-1 flex-col"
                            @submit.prevent="submit"
                        >
                            <div
                                class="flex-1 space-y-6 overflow-y-auto px-6 py-5"
                            >
                                <div class="space-y-4">
                                    <p
                                        class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Datos personales
                                    </p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Nombre
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <input
                                                    v-model="form.first_name"
                                                    type="text"
                                                    required
                                                    maxlength="100"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="
                                                    form.errors.first_name
                                                "
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Apellidos
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <input
                                                    v-model="form.last_name"
                                                    type="text"
                                                    required
                                                    maxlength="150"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.last_name"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Teléfono
                                                <input
                                                    v-model="form.phone"
                                                    type="text"
                                                    maxlength="20"
                                                    placeholder="Ej. 8711234567"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.phone"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Sexo
                                                <select
                                                    v-model="form.sex"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                >
                                                    <option value="">
                                                        Seleccionar...
                                                    </option>
                                                    <option value="Femenino">
                                                        Femenino
                                                    </option>
                                                    <option value="Masculino">
                                                        Masculino
                                                    </option>
                                                    <option value="Otro">
                                                        Otro
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.sex"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >CURP
                                                <input
                                                    v-model="form.curp"
                                                    type="text"
                                                    maxlength="18"
                                                    placeholder="18 caracteres"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 font-mono text-sm font-normal uppercase outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.curp"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Número de Seguro Social (NSS)
                                                <input
                                                    v-model="form.ssn"
                                                    type="text"
                                                    maxlength="11"
                                                    placeholder="Hasta 11 dígitos"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 font-mono text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.ssn"
                                            />
                                        </div>

                                        <div class="sm:col-span-2">
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Nacionalidad
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <select
                                                    v-model="
                                                        form.nationality_id
                                                    "
                                                    required
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                >
                                                    <option
                                                        v-for="nat in catalogs.nationalities"
                                                        :key="nat.id"
                                                        :value="nat.id"
                                                    >
                                                        {{ nat.nationality }}
                                                        {{
                                                            nat.country
                                                                ? `(${nat.country})`
                                                                : ''
                                                        }}
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="
                                                    form.errors.nationality_id
                                                "
                                            />
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="space-y-4 border-t border-slate-100 pt-5"
                                >
                                    <p
                                        class="text-[11px] font-semibold tracking-wider text-slate-500 uppercase"
                                    >
                                        Datos laborales y nómina
                                    </p>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Clave de empleado
                                                <input
                                                    v-model="form.code"
                                                    type="text"
                                                    maxlength="50"
                                                    placeholder="Auto (ej. EMP-001)"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 font-mono text-sm font-normal uppercase outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.code"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Fecha de ingreso
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <input
                                                    v-model="form.hire_date"
                                                    type="date"
                                                    required
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                />
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.hire_date"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Área
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <select
                                                    v-model="form.area_id"
                                                    required
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                    @change="handleAreaChange"
                                                >
                                                    <option
                                                        v-for="area in catalogs.areas"
                                                        :key="area.id"
                                                        :value="area.id"
                                                    >
                                                        {{ area.code }} —
                                                        {{ area.name }}
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.area_id"
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Puesto
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <select
                                                    v-model="form.position_id"
                                                    required
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                >
                                                    <option
                                                        v-for="pos in availablePositions"
                                                        :key="pos.id"
                                                        :value="pos.id"
                                                    >
                                                        {{ pos.code }} —
                                                        {{ pos.name }}
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="
                                                    form.errors.position_id
                                                "
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Frecuencia de nómina
                                                <span class="text-red-600"
                                                    >*</span
                                                >
                                                <select
                                                    v-model="
                                                        form.payroll_frequency_id
                                                    "
                                                    required
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                >
                                                    <option
                                                        v-for="freq in catalogs.frequencies"
                                                        :key="freq.id"
                                                        :value="freq.id"
                                                    >
                                                        {{ freq.name }} ({{
                                                            freq.days
                                                        }}
                                                        días)
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="
                                                    form.errors
                                                        .payroll_frequency_id
                                                "
                                            />
                                        </div>

                                        <div>
                                            <label
                                                class="block text-xs font-semibold text-slate-700"
                                                >Estado
                                                <select
                                                    v-model="form.status"
                                                    class="mt-1.5 block h-10 w-full rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal outline-none focus:border-[#23c6c8]"
                                                >
                                                    <option value="activo">
                                                        Activo
                                                    </option>
                                                    <option value="inactivo">
                                                        Inactivo
                                                    </option>
                                                </select>
                                            </label>
                                            <InputError
                                                class="mt-1"
                                                :message="form.errors.status"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center justify-end gap-2 border-t border-slate-100 px-6 py-4"
                            >
                                <button
                                    type="button"
                                    class="h-10 rounded-lg px-4 text-xs font-semibold text-slate-600 hover:bg-slate-100"
                                    @click="emit('close')"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="h-10 rounded-lg bg-[#23c6c8] px-4 text-xs font-semibold text-white transition hover:bg-[#1bb2b4] disabled:opacity-50"
                                >
                                    {{
                                        form.processing
                                            ? 'Guardando...'
                                            : employee
                                              ? 'Guardar cambios'
                                              : 'Registrar empleado'
                                    }}
                                </button>
                            </div>
                        </form>
                    </aside>
                </NexusViewTransition>
            </div>
        </Transition>
    </Teleport>
</template>
