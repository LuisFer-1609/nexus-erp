<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

type Company = { id: number; name: string; is_active: boolean };
type Role = { id: number; name: string };
type CompanyUser = {
    id: number;
    name: string;
    email: string;
    company_id: number;
    role_id: number;
    company_name: string;
    role_name: string;
    password_set_at: string | null;
};

const props = defineProps<{
    companies: Company[];
    roles: Role[];
    users: CompanyUser[];
}>();

const message = ref('');
const error = ref('');
const companyForm = useForm({ name: '' });
const userForm = useForm({ name: '', email: '', company_id: '', role_id: '' });
const assignments = reactive<
    Record<number, { company_id: number; role_id: number }>
>({});

props.users.forEach((user) => {
    assignments[user.id] = {
        company_id: user.company_id,
        role_id: user.role_id,
    };
});

function createCompany() {
    companyForm.post('/admin/companies', {
        onSuccess: () => {
            companyForm.reset();
            message.value = 'Empresa creada.';
        },
    });
}

function createUser() {
    userForm.post('/admin/users', {
        onSuccess: () => {
            userForm.reset();
            message.value =
                'Usuario creado. Se procesó su invitación por correo.';
        },
    });
}

function updateUser(user: CompanyUser) {
    const assignment = assignments[user.id];
    if (!assignment) return;

    router.patch(`/admin/users/${user.id}`, assignment, {
        onSuccess: () => (message.value = 'Asignación actualizada.'),
        onError: (errors) => {
            error.value =
                errors.company_id ||
                errors.role_id ||
                'No se pudo actualizar el usuario.';
        },
    });
}

function resend(user: CompanyUser) {
    router.post(
        `/admin/users/${user.id}/resend-invitation`,
        {},
        {
            onSuccess: () =>
                (message.value = 'Invitación procesada nuevamente.'),
            onError: () => {
                error.value = 'No se pudo reenviar la invitación.';
            },
        },
    );
}
</script>

<template>
    <Head title="Administración de la plataforma" />
    <div class="mb-8">
        <p class="text-sm font-medium text-cyan-700">
            NexusERP / Administración
        </p>
        <h1 class="mt-1 text-3xl font-semibold tracking-tight">
            Empresas y accesos
        </h1>
        <p class="mt-2 text-sm text-slate-600">
            Crea empresas y asigna usuarios. Este panel no muestra información
            del CRM de las empresas.
        </p>
    </div>

    <p
        v-if="message"
        role="status"
        class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"
    >
        {{ message }}
    </p>
    <p
        v-if="error"
        role="alert"
        class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
        {{ error }}
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Nueva empresa</h2>
            <form class="mt-5 flex gap-3" @submit.prevent="createCompany">
                <div class="min-w-0 flex-1">
                    <label
                        for="company-name"
                        class="mb-1 block text-sm font-medium"
                        >Nombre</label
                    >
                    <input
                        id="company-name"
                        v-model="companyForm.name"
                        required
                        maxlength="255"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="companyForm.errors.name"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ companyForm.errors.name }}
                    </p>
                </div>
                <button
                    type="submit"
                    :disabled="companyForm.processing"
                    class="self-end rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Crear
                </button>
            </form>
            <ul class="mt-6 divide-y divide-slate-100">
                <li
                    v-for="company in companies"
                    :key="company.id"
                    class="flex justify-between py-2 text-sm"
                >
                    <span>{{ company.name }}</span
                    ><span class="text-slate-500">#{{ company.id }}</span>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Invitar usuario</h2>
            <p class="mt-1 text-sm text-slate-500">
                Recibirá un enlace para establecer su contraseña antes de
                iniciar sesión.
            </p>
            <form class="mt-5 grid gap-3" @submit.prevent="createUser">
                <div>
                    <label
                        for="user-name"
                        class="mb-1 block text-sm font-medium"
                        >Nombre</label
                    >
                    <input
                        id="user-name"
                        v-model="userForm.name"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="userForm.errors.name"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ userForm.errors.name }}
                    </p>
                </div>
                <div>
                    <label
                        for="user-email"
                        class="mb-1 block text-sm font-medium"
                        >Correo</label
                    >
                    <input
                        id="user-email"
                        v-model="userForm.email"
                        type="email"
                        required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                    />
                    <p
                        v-if="userForm.errors.email"
                        class="mt-1 text-sm text-red-600"
                    >
                        {{ userForm.errors.email }}
                    </p>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label
                            for="user-company"
                            class="mb-1 block text-sm font-medium"
                            >Empresa</label
                        >
                        <select
                            id="user-company"
                            v-model="userForm.company_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="" disabled>Selecciona una</option>
                            <option
                                v-for="company in companies.filter(
                                    (item) => item.is_active,
                                )"
                                :key="company.id"
                                :value="company.id"
                            >
                                {{ company.name }}
                            </option>
                        </select>
                        <p
                            v-if="userForm.errors.company_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ userForm.errors.company_id }}
                        </p>
                    </div>
                    <div>
                        <label
                            for="user-role"
                            class="mb-1 block text-sm font-medium"
                            >Rol</label
                        >
                        <select
                            id="user-role"
                            v-model="userForm.role_id"
                            required
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        >
                            <option value="" disabled>Selecciona uno</option>
                            <option
                                v-for="role in roles"
                                :key="role.id"
                                :value="role.id"
                            >
                                {{ role.name }}
                            </option>
                        </select>
                        <p
                            v-if="userForm.errors.role_id"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ userForm.errors.role_id }}
                        </p>
                    </div>
                </div>
                <button
                    type="submit"
                    :disabled="userForm.processing"
                    class="mt-2 rounded-lg bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Crear y enviar invitación
                </button>
            </form>
        </section>
    </div>

    <section class="mt-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Usuarios de empresas</h2>
        <p v-if="users.length === 0" class="mt-4 text-sm text-slate-500">
            Aún no hay usuarios invitados.
        </p>
        <div v-else class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2">Usuario</th>
                        <th>Empresa</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in users"
                        :key="user.id"
                        class="border-b border-slate-100"
                    >
                        <td class="py-3">
                            <span class="font-medium">{{ user.name }}</span
                            ><br /><span class="text-slate-500">{{
                                user.email
                            }}</span>
                        </td>
                        <td>
                            <select
                                v-if="assignments[user.id]"
                                v-model.number="
                                    assignments[user.id]!.company_id
                                "
                                :disabled="!!user.password_set_at"
                                class="rounded border border-slate-300 p-2 disabled:bg-slate-100"
                            >
                                <option
                                    v-for="company in companies.filter(
                                        (item) => item.is_active,
                                    )"
                                    :key="company.id"
                                    :value="company.id"
                                >
                                    {{ company.name }}
                                </option>
                            </select>
                        </td>
                        <td>
                            <select
                                v-if="assignments[user.id]"
                                v-model.number="assignments[user.id]!.role_id"
                                class="rounded border border-slate-300 p-2"
                            >
                                <option
                                    v-for="role in roles"
                                    :key="role.id"
                                    :value="role.id"
                                >
                                    {{ role.name }}
                                </option>
                            </select>
                        </td>
                        <td>
                            {{ user.password_set_at ? 'Activo' : 'Pendiente' }}
                        </td>
                        <td class="space-x-3 whitespace-nowrap">
                            <button
                                type="button"
                                class="font-medium text-cyan-700"
                                @click="updateUser(user)"
                            >
                                Guardar</button
                            ><button
                                v-if="!user.password_set_at"
                                type="button"
                                class="font-medium text-slate-600"
                                @click="resend(user)"
                            >
                                Reenviar
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</template>
