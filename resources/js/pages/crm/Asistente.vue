<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Bot, Plus, Send, Sparkles } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const conversations = [
    ['Resumen de ventas', 'Hace 2 min'],
    ['Leads de Facebook', 'Hoy 10:12'],
    ['Asistencias del equipo', 'Ayer'],
    ['Pre-nómina septiembre', '26 sept'],
];
</script>

<template>
    <Head title="Asistente IA" />

    <NexusPageHeader
        eyebrow="Asistente empresarial"
        title="Nexus Assistant"
        description="Consulta información de clientes, ventas y personal con contexto seguro."
    />

    <div class="grid min-h-[620px] overflow-hidden rounded-[10px] border border-slate-200 bg-white lg:grid-cols-[270px_1fr]">
        <aside class="border-b border-slate-200 bg-slate-50/70 p-4 lg:border-b-0 lg:border-r">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-xs font-semibold text-slate-800">Conversaciones</p>
                <button class="flex size-7 items-center justify-center rounded-md text-slate-500 transition hover:bg-white hover:text-[#139b9e]"><Plus class="size-3.5" /></button>
            </div>
            <button class="mb-3 flex h-9 w-full items-center justify-center gap-2 rounded-lg bg-[#0a161e] text-[10px] font-semibold text-white transition hover:bg-[#162732]">
                <Plus class="size-3.5" /> Nueva conversación
            </button>
            <div class="space-y-1.5">
                <button v-for="(conversation, index) in conversations" :key="conversation[0]" class="w-full rounded-lg px-3 py-2.5 text-left transition hover:bg-white" :class="index === 0 ? 'bg-white shadow-sm ring-1 ring-slate-200' : ''">
                    <p class="truncate text-[10px] font-semibold text-slate-800">{{ conversation[0] }}</p>
                    <p class="mt-1 text-[9px] text-slate-400">{{ conversation[1] }}</p>
                </button>
            </div>
        </aside>

        <section class="flex min-h-[620px] flex-col">
            <div class="border-b border-slate-100 px-5 py-3">
                <p class="text-[10px] font-medium text-slate-500">Acme Marketing · Administrador</p>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto p-5 md:p-7">
                <div class="ml-auto max-w-[70%] rounded-2xl rounded-br-md bg-[#0a161e] px-4 py-3 text-[11px] leading-5 text-white shadow-sm">
                    ¿Cuántos leads nuevos tuvimos esta semana y cuáles campañas generaron más?
                </div>

                <div class="flex max-w-[88%] gap-3">
                    <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-[#139b9e]"><Bot class="size-4" /></div>
                    <div>
                        <p class="rounded-2xl rounded-tl-md bg-slate-50 px-4 py-3 text-[11px] leading-5 text-slate-700">
                            Esta semana se registraron 46 leads nuevos. La campaña “ERP para PYMES” generó 21 leads y “Control de nómina” generó 14. El resto provino de campañas de remarketing y formularios orgánicos.
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <NexusBadge label="CRM · 46 registros" tone="cyan" />
                            <NexusBadge label="Meta · 4 campañas" tone="blue" />
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2 text-[10px] text-slate-400">
                    <Sparkles class="size-3.5 animate-pulse text-[#23c6c8]" />
                    Generando comparación por tasa de conversión…
                </div>

                <button class="rounded-lg border border-dashed border-[#23c6c8]/50 bg-cyan-50/40 px-3 py-2 text-left text-[10px] font-medium text-[#0e7f82] transition hover:bg-cyan-50">
                    Sugerencia: Muéstrame los leads por etapa del embudo
                </button>
            </div>

            <div class="border-t border-slate-100 p-4">
                <div class="flex items-end gap-2 rounded-xl border border-slate-200 bg-slate-50 p-2 transition focus-within:border-[#23c6c8]/70 focus-within:bg-white focus-within:ring-2 focus-within:ring-[#23c6c8]/10">
                    <textarea rows="2" class="max-h-32 flex-1 resize-none bg-transparent px-2 py-1 text-[11px] outline-none" placeholder="Pregunta sobre clientes, ventas o empleados..." />
                    <button class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-[#139b9e] text-white transition hover:-translate-y-0.5 hover:bg-[#108b8e] motion-reduce:transform-none"><Send class="size-4" /></button>
                </div>
            </div>
        </section>
    </div>
</template>
