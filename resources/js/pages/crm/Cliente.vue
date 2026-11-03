<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Mail, Phone } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';

defineProps<{ clienteId?: string }>();

const activity = [
    { date: 'Hoy · 12:34', title: 'Seguimiento por teléfono', body: 'Luis registró una llamada. El cliente mostró interés en el plan Profesional.' },
    { date: 'Hoy · 10:18', title: 'Nota interna', body: 'Solicitó contacto después de las 12:00 y preguntó por integración con Instagram.' },
    { date: 'Ayer · 17:02', title: 'Correo enviado', body: 'Se envió información comercial y una propuesta inicial.' },
    { date: '27 sept · 09:45', title: 'Lead capturado automáticamente', body: 'Facebook · Campaña “ERP para PYMES” · Formulario “Solicitar demo”.' },
];
</script>

<template>
    <Head title="Ficha de cliente" />

    <div class="mb-5 text-[11px] font-medium text-slate-500">
        <Link href="/crm/clientes" class="transition hover:text-[#139b9e]">CRM / Clientes</Link>
        <span> / CL-{{ clienteId || '1048' }}</span>
    </div>

    <section class="rounded-[10px] border border-slate-200 bg-white p-5 md:p-6">
        <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-14 items-center justify-center rounded-full bg-cyan-50 text-sm font-semibold text-[#139b9e]">ML</div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">María López</h1>
                        <NexusBadge label="Contactado" tone="green" />
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Ficha comercial y seguimiento del lead.</p>
                    <p class="mt-2 text-[10px] text-slate-500">Lead #CL-1048 · Facebook Lead Ads</p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button class="inline-flex h-9 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 text-[11px] font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:bg-slate-50 hover:shadow-sm motion-reduce:transform-none"><Phone class="size-3.5" />Llamar</button>
                <button class="inline-flex h-9 items-center gap-2 rounded-lg bg-[#139b9e] px-3.5 text-[11px] font-semibold text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] hover:shadow-md motion-reduce:transform-none"><Mail class="size-3.5" />Enviar correo</button>
            </div>
        </div>
    </section>

    <section class="mt-5 rounded-[10px] border border-slate-200 bg-white">
        <div class="border-b border-slate-100 px-5 pt-4">
            <h2 class="text-sm font-semibold text-slate-900">Actividad del cliente</h2>
            <div class="mt-4 flex gap-6 text-[11px]">
                <button class="border-b-2 border-[#23c6c8] pb-3 font-semibold text-slate-900">Actividad</button>
                <button class="pb-3 font-medium text-slate-500 hover:text-slate-900">Notas</button>
                <button class="pb-3 font-medium text-slate-500 hover:text-slate-900">Tareas</button>
            </div>
        </div>

        <div class="px-5 py-5 md:px-7">
            <div class="relative ml-2 border-l border-slate-200">
                <article v-for="(item, index) in activity" :key="item.date" class="relative pb-6 pl-6 last:pb-1">
                    <span class="absolute -left-[5px] top-1 size-2.5 rounded-full border-2 border-white bg-[#23c6c8] shadow-sm" />
                    <p class="text-[9px] font-medium text-slate-400">{{ item.date }}</p>
                    <h3 class="mt-1 text-[11px] font-semibold text-slate-900">{{ item.title }}</h3>
                    <p class="mt-1 max-w-4xl text-[10px] leading-5 text-slate-500">{{ item.body }}</p>
                </article>
            </div>

            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3 transition focus-within:border-[#23c6c8]/70 focus-within:bg-white focus-within:ring-2 focus-within:ring-[#23c6c8]/10">
                <textarea rows="3" class="w-full resize-none bg-transparent text-[11px] text-slate-700 outline-none placeholder:text-slate-400" placeholder="Escribe una nota o registra una interacción..." />
                <div class="mt-2 flex justify-end">
                    <button class="rounded-lg bg-[#139b9e] px-4 py-2 text-[10px] font-semibold text-white transition hover:bg-[#108b8e]">Agregar nota</button>
                </div>
            </div>
        </div>
    </section>
</template>
