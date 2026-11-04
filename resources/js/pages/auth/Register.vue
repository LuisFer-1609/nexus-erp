<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crea tu cuenta',
        description: 'Ingresa tus datos para comenzar a trabajar con NexusERP.',
    },
});

const inputClasses =
    'h-12 rounded-[7px] border-[#e2e8f0] bg-white px-4 text-base text-[#111827] shadow-none placeholder:text-[#94a3b8] focus-visible:border-[#23c6c8] focus-visible:ring-2 focus-visible:ring-[#23c6c8]/15 dark:border-[#e2e8f0] dark:bg-white dark:text-[#111827] dark:placeholder:text-[#94a3b8]';
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col"
    >
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="space-y-2 sm:col-span-2">
                <Label
                    for="name"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Nombre completo</Label
                >
                <Input
                    id="name"
                    type="text"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    placeholder="Tu nombre"
                    :class="inputClasses"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="space-y-2 sm:col-span-2">
                <Label
                    for="email"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Correo electrónico</Label
                >
                <Input
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="tu@empresa.com"
                    :class="inputClasses"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="space-y-2">
                <Label
                    for="password"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Contraseña</Label
                >
                <PasswordInput
                    id="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    placeholder="Contraseña"
                    :passwordrules="passwordRules"
                    :class="inputClasses"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="space-y-2">
                <Label
                    for="password_confirmation"
                    class="text-[13px] font-semibold text-[#111827] dark:text-[#111827]"
                    >Confirmar contraseña</Label
                >
                <PasswordInput
                    id="password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder="Repite tu contraseña"
                    :passwordrules="passwordRules"
                    :class="inputClasses"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-md bg-[#23c6c8] text-sm font-semibold text-white shadow-none hover:bg-[#1eb1b3] focus-visible:ring-[#23c6c8]/30 sm:col-span-2"
                :tabindex="5"
                :disabled="processing"
                data-test="register-user-button"
            >
                <Spinner v-if="processing" />
                Crear cuenta
            </Button>
        </div>

        <p class="mt-5 text-center text-xs text-[#64748b] dark:text-[#64748b]">
            ¿Ya tienes una cuenta?
            <TextLink
                :href="login()"
                class="font-semibold text-[#139b9e] no-underline dark:text-[#139b9e]"
                :tabindex="6"
            >
                Inicia sesión
            </TextLink>
        </p>
    </Form>
</template>
