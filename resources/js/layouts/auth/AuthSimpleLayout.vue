<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main class="min-h-svh bg-[#f2f6f8]">
        <div
            class="grid min-h-svh w-full overflow-hidden bg-white lg:grid-cols-[40%_60%]"
        >
            <section
                class="relative hidden overflow-hidden border-t-[6px] border-[#23c6c8] bg-[#0a161e] px-12 pt-12 pb-7 text-white lg:flex lg:flex-col"
            >
                <div>
                    <div class="flex items-baseline gap-1">
                        <span
                            class="text-[32px] leading-none font-semibold tracking-tight"
                            >Nexus</span
                        >
                        <span
                            class="text-sm font-semibold tracking-[0.08em] text-[#23c6c8]"
                            >ERP</span
                        >
                    </div>
                    <p class="mt-3 text-sm font-medium text-[#aabec9]">
                        Gestión empresarial en un solo lugar
                    </p>
                </div>

                <div class="mt-[82px] max-w-[420px]">
                    <h2
                        class="text-[40px] leading-[1.15] font-semibold tracking-tight"
                    >
                        Centraliza CRM,<br />
                        personal y pre-nómina.
                    </h2>
                    <p class="mt-7 text-base leading-7 text-[#aabec9]">
                        Accede únicamente a la información de la empresa y
                        módulos autorizados para tu cuenta.
                    </p>

                    <div class="mt-[68px] space-y-8">
                        <div
                            class="flex items-center gap-4 text-[15px] font-medium text-[#ebf2f6]"
                        >
                            <span
                                class="relative flex size-[30px] shrink-0 items-center justify-center rounded-full bg-[#23c6c8]"
                            >
                                <img
                                    class="absolute inset-0 block size-[30px]"
                                    src="/images/auth-feature-check.svg"
                                    alt=""
                                    width="30"
                                    height="30"
                                />
                                <span
                                    class="absolute text-[11px] font-semibold text-white"
                                    >✓</span
                                >
                            </span>
                            CRM y leads
                        </div>
                        <div
                            class="flex items-center gap-4 text-[15px] font-medium text-[#ebf2f6]"
                        >
                            <span
                                class="relative flex size-[30px] shrink-0 items-center justify-center rounded-full bg-[#23c6c8]"
                            >
                                <img
                                    class="absolute inset-0 block size-[30px]"
                                    src="/images/auth-feature-check.svg"
                                    alt=""
                                    width="30"
                                    height="30"
                                />
                                <span
                                    class="absolute text-[11px] font-semibold text-white"
                                    >✓</span
                                >
                            </span>
                            Asistencias y pre-nómina
                        </div>
                        <div
                            class="flex items-center gap-4 text-[15px] font-medium text-[#ebf2f6]"
                        >
                            <span
                                class="relative flex size-[30px] shrink-0 items-center justify-center rounded-full bg-[#23c6c8]"
                            >
                                <img
                                    class="absolute inset-0 block size-[30px]"
                                    src="/images/auth-feature-check.svg"
                                    alt=""
                                    width="30"
                                    height="30"
                                />
                                <span
                                    class="absolute text-[11px] font-semibold text-white"
                                    >✓</span
                                >
                            </span>
                            Asistente empresarial con IA
                        </div>
                    </div>
                </div>

                <p class="mt-auto text-[11px] font-medium text-[#648090]">
                    NexusERP 2.0 · MVP académico
                </p>
            </section>

            <section
                class="flex items-center justify-center px-6 py-10 sm:px-10 lg:px-[72px] lg:py-8"
            >
                <div class="w-full max-w-[540px]">
                    <div class="mb-9">
                        <div class="mb-8 flex items-baseline gap-1 lg:hidden">
                            <span
                                class="text-2xl font-semibold tracking-tight text-[#111827]"
                                >Nexus</span
                            >
                            <span
                                class="text-[11px] font-semibold tracking-[0.08em] text-[#139b9e]"
                                >ERP</span
                            >
                        </div>
                        <h1
                            class="text-[32px] leading-tight font-semibold tracking-tight text-[#111827] dark:text-[#111827]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            class="mt-2 text-sm leading-6 text-[#64748b] dark:text-[#64748b]"
                        >
                            {{ description }}
                        </p>
                    </div>

                    <slot />

                    <div class="mt-7 rounded-lg bg-[#ecfdf5] px-5 py-4">
                        <p
                            class="text-[13px] font-semibold text-[#10b981] dark:text-[#10b981]"
                        >
                            Acceso protegido por empresa
                        </p>
                        <p
                            class="mt-1.5 text-xs leading-5 text-[#10b981] dark:text-[#10b981]"
                        >
                            Los datos se filtran automáticamente según la
                            empresa activa y los permisos de tu usuario.
                        </p>
                    </div>

                    <p
                        class="mt-5 text-[11px] text-[#64748b] dark:text-[#64748b]"
                    >
                        © 2026 NexusERP · Uso académico
                    </p>
                </div>
            </section>
        </div>
    </main>
</template>
