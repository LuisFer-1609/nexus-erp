<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="min-h-svh bg-[#f6f8fa] p-3 sm:p-5 lg:p-7">
        <div class="mx-auto grid min-h-[calc(100svh-2.5rem)] w-full max-w-[1240px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl shadow-slate-200/50 lg:grid-cols-[0.95fr_1.05fr]">
            <section class="relative hidden overflow-hidden bg-[#0a161e] p-10 text-white lg:flex lg:flex-col lg:justify-between">
                <div class="absolute -right-24 -top-24 size-72 rounded-full bg-[#23c6c8]/10 blur-3xl" />
                <div class="absolute -bottom-28 -left-28 size-80 rounded-full bg-[#23c6c8]/10 blur-3xl" />

                <div class="relative">
                    <div class="inline-flex items-baseline gap-1">
                        <span class="text-2xl font-semibold tracking-tight">Nexus</span>
                        <span class="text-xs font-semibold tracking-[0.12em] text-[#23c6c8]">ERP</span>
                    </div>
                </div>

                <div class="relative max-w-md">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#7497ab]">Gestión empresarial</p>
                    <h2 class="mt-4 text-4xl font-semibold leading-tight tracking-tight">
                        Centraliza CRM,<br />
                        personal y pre-nómina.
                    </h2>
                    <p class="mt-5 max-w-sm text-sm leading-6 text-[#96acb9]">
                        Accede a los módulos autorizados para tu cuenta y trabaja con la información de tu empresa desde un solo lugar.
                    </p>

                    <div class="mt-8 space-y-3">
                        <div class="flex items-center gap-3 text-sm text-[#dce6eb]">
                            <span class="flex size-6 items-center justify-center rounded-full bg-[#23c6c8]/10 text-[10px] font-bold text-[#23c6c8]">✓</span>
                            CRM y seguimiento de leads
                        </div>
                        <div class="flex items-center gap-3 text-sm text-[#dce6eb]">
                            <span class="flex size-6 items-center justify-center rounded-full bg-[#23c6c8]/10 text-[10px] font-bold text-[#23c6c8]">✓</span>
                            Asistencias y pre-nómina
                        </div>
                        <div class="flex items-center gap-3 text-sm text-[#dce6eb]">
                            <span class="flex size-6 items-center justify-center rounded-full bg-[#23c6c8]/10 text-[10px] font-bold text-[#23c6c8]">✓</span>
                            Asistente empresarial con IA
                        </div>
                    </div>
                </div>

                <p class="relative text-[10px] font-medium text-[#5f7887]">NexusERP 2.0 · MVP académico</p>
            </section>

            <section class="flex items-center justify-center px-5 py-10 sm:px-10 lg:px-16">
                <div class="w-full max-w-[430px] animate-in fade-in slide-in-from-bottom-2 duration-300 motion-reduce:animate-none">
                    <div class="mb-8">
                        <div class="mb-6 inline-flex items-baseline gap-1 lg:hidden">
                            <span class="text-xl font-semibold text-slate-900">Nexus</span>
                            <span class="text-[10px] font-semibold tracking-[0.1em] text-[#139b9e]">ERP</span>
                        </div>
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">{{ title }}</h1>
                        <p class="mt-2 text-sm leading-6 text-slate-500">{{ description }}</p>
                    </div>

                    <slot />

                    <div class="mt-7 rounded-lg border border-cyan-100 bg-cyan-50/50 p-3">
                        <p class="text-[10px] font-semibold text-[#0e7f82]">Acceso protegido por empresa</p>
                        <p class="mt-1 text-[9px] leading-4 text-slate-500">
                            Al iniciar sesión, NexusERP utiliza automáticamente la empresa y los permisos asignados a tu usuario.
                        </p>
                    </div>

                    <p class="mt-7 text-center text-[9px] text-slate-400">© 2026 NexusERP · Uso académico</p>
                </div>
            </section>
        </div>
    </div>
</template>
