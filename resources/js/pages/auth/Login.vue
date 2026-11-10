<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/PasskeyVerify.vue';

defineOptions({
    layout: {
        title: 'Bienvenido de nuevo',
        description: 'Ingresa tus credenciales para acceder a NexusERP.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        class="mb-4 rounded-lg border border-emerald-100 bg-emerald-50 px-3 py-2 text-center text-[11px] font-medium text-emerald-700"
    >
        {{ status }}
    </div>

    <div
        class="mb-5 [&_button]:w-full [&_button]:rounded-lg [&_button]:border-slate-200 [&_button]:text-[11px]"
    >
        <PasskeyVerify />
    </div>

    <div class="relative mb-5 flex items-center">
        <span class="h-px flex-1 bg-slate-200" />
        <span
            class="px-3 text-[9px] font-medium tracking-[0.12em] text-slate-400 uppercase"
            >o con contraseña</span
        >
        <span class="h-px flex-1 bg-slate-200" />
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label
                    for="email"
                    class="text-[11px] font-semibold text-slate-700"
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
                    class="h-10 rounded-lg border-slate-200 bg-slate-50 text-[12px] transition focus-visible:border-[#23c6c8] focus-visible:bg-white focus-visible:ring-[#23c6c8]/15"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label
                        for="password"
                        class="text-[11px] font-semibold text-slate-700"
                        >Contraseña</Label
                    >
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-[10px] font-semibold text-[#139b9e] no-underline transition hover:text-[#0e7f82]"
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
                    class="h-10 rounded-lg border-slate-200 bg-slate-50 text-[12px]"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label
                    for="remember"
                    class="flex items-center space-x-2.5 text-[10px] font-medium text-slate-500"
                >
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>Mantener sesión iniciada</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-1 h-10 w-full rounded-lg bg-[#139b9e] text-[11px] font-semibold text-white shadow-none transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md hover:shadow-cyan-900/10 motion-reduce:transform-none"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Ingresar a NexusERP
            </Button>
        </div>
    </Form>
</template>

