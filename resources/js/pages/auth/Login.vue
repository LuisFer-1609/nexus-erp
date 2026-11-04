<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Bienvenido de nuevo',
        description:
            'Ingresa tus credenciales y selecciona la empresa con la que trabajarás.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const inputClasses =
    'h-12 rounded-[7px] border-[#e2e8f0] bg-white px-4 text-base text-[#111827] shadow-none placeholder:text-[#94a3b8] focus-visible:border-[#23c6c8] focus-visible:ring-2 focus-visible:ring-[#23c6c8]/15 dark:border-[#e2e8f0] dark:bg-white dark:text-[#111827] dark:placeholder:text-[#94a3b8]';
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-5 rounded-lg bg-[#ecfdf5] px-4 py-3 text-[13px] font-medium text-[#047857]"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col"
    >
        <div class="space-y-6">
            <div class="space-y-2">
                <Label
                    for="email"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Correo electrónico</Label
                >
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="tu@empresa.com"
                    :class="inputClasses"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                        >Contraseña</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-xs font-semibold text-[#139b9e] no-underline hover:text-[#0e7f82] dark:text-[#139b9e]"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Contraseña"
                    :class="inputClasses"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="space-y-2">
                <Label
                    for="company"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Empresa activa</Label
                >
                <div
                    id="company"
                    class="flex h-14 items-center rounded-[7px] border border-[#e2e8f0] bg-white px-4 dark:border-[#e2e8f0] dark:bg-white"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-md bg-[#eff6ff] text-[13px] font-semibold text-[#2563eb]"
                        >A</span
                    >
                    <span class="ml-3 min-w-0 flex-1">
                        <span
                            class="block truncate text-sm font-semibold text-[#111827] dark:text-[#111827]"
                            >Acme Marketing</span
                        >
                        <span
                            class="mt-0.5 block text-xs text-[#64748b] dark:text-[#64748b]"
                            >Rol: Administrador</span
                        >
                    </span>
                    <span class="text-sm font-medium text-[#64748b]">⌄</span>
                </div>
            </div>

            <Button
                type="submit"
                class="h-11 w-full rounded-md bg-[#23c6c8] text-sm font-semibold text-white shadow-none hover:bg-[#1eb1b3] focus-visible:ring-[#23c6c8]/30"
                :tabindex="3"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Ingresar a NexusERP
            </Button>
        </div>

        <p class="mt-5 text-center text-xs text-[#64748b] dark:text-[#64748b]">
            ¿No tienes una cuenta?
            <TextLink
                :href="register()"
                :tabindex="4"
                class="font-semibold text-[#139b9e] no-underline dark:text-[#139b9e]"
            >
                Regístrate
            </TextLink>
        </p>
    </Form>
</template>
