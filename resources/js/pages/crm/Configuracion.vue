<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Facebook, Instagram, Megaphone, ShieldCheck } from '@lucide/vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

const assets = [
    { icon: Facebook, name: 'Facebook · Acme MX', type: 'Página' },
    { icon: Instagram, name: 'Instagram · @acmemarketing', type: 'Cuenta' },
    { icon: Megaphone, name: 'Meta Ads · Cuenta principal', type: 'Publicidad' },
    { icon: Facebook, name: 'Facebook · Acme Laguna', type: 'Página' },
];

const roles = [
    { role: 'Administrador', detail: 'Acceso completo', users: '4 usuarios' },
    { role: 'Ventas', detail: 'CRM y clientes', users: '8 usuarios' },
    { role: 'RH', detail: 'Empleados y pre-nómina', users: '3 usuarios' },
];
</script>

<template>
    <Head title="Configuración" />

    <NexusPageHeader eyebrow="CRM / Configuración" title="Configuración" description="Administra la empresa, usuarios, roles e integraciones del CRM." />

    <div class="mb-5 flex gap-6 border-b border-slate-200 text-[11px]">
        <button class="pb-3 font-medium text-slate-500 transition hover:text-slate-900">Empresa</button>
        <button class="pb-3 font-medium text-slate-500 transition hover:text-slate-900">Usuarios y roles</button>
        <button class="border-b-2 border-[#23c6c8] pb-3 font-semibold text-slate-900">Integraciones</button>
    </div>

    <div class="grid gap-5 xl:grid-cols-[1.35fr_0.85fr]">
        <div class="space-y-5">
            <section class="rounded-[10px] border border-slate-200 bg-white p-5">
                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm font-semibold text-slate-900">Meta Business</h2>
                            <NexusBadge label="Conectado" tone="green" />
                        </div>
                        <p class="mt-2 max-w-xl text-[10px] leading-5 text-slate-500">Gestiona las cuentas de Facebook e Instagram vinculadas a esta empresa.</p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-[9px] text-slate-400">Cuenta conectada</p>
                        <p class="mt-1 text-[11px] font-semibold text-slate-800">Acme Marketing</p>
                    </div>
                </div>

                <div class="mt-5 flex flex-col gap-3 rounded-lg bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-[9px] text-slate-400">Activos vinculados</p><p class="mt-1 text-sm font-semibold text-slate-900">6 de 10 cuentas</p></div>
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-[#139b9e] px-3.5 py-2 text-[10px] font-semibold text-white transition hover:bg-[#108b8e]">Administrar cuentas</button>
                        <button class="rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-[10px] font-semibold text-slate-600 transition hover:bg-slate-50 hover:text-rose-600">Desconectar</button>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-[10px] border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Cuentas vinculadas</h2>
                    <p class="mt-1 text-[10px] text-slate-500">Activos disponibles para campañas y captación de leads.</p>
                </div>
                <div class="divide-y divide-slate-100">
                    <div v-for="asset in assets" :key="asset.name" class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-slate-50/80">
                        <div class="flex size-8 items-center justify-center rounded-lg bg-slate-50 text-slate-600"><component :is="asset.icon" class="size-4" /></div>
                        <div class="min-w-0 flex-1"><p class="truncate text-[11px] font-semibold text-slate-900">{{ asset.name }}</p><p class="mt-0.5 text-[9px] text-slate-500">{{ asset.type }}</p></div>
                        <NexusBadge label="Activa" tone="green" />
                    </div>
                </div>
            </section>
        </div>

        <section class="h-fit overflow-hidden rounded-[10px] border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <div class="flex items-center gap-2"><ShieldCheck class="size-4 text-[#139b9e]" /><h2 class="text-sm font-semibold text-slate-900">Usuarios y roles</h2></div>
                <p class="mt-1 text-[10px] text-slate-500">Permisos básicos definidos para el MVP.</p>
            </div>
            <div class="divide-y divide-slate-100">
                <div v-for="role in roles" :key="role.role" class="px-5 py-4">
                    <div class="flex items-center justify-between gap-3">
                        <div><p class="text-[11px] font-semibold text-slate-900">{{ role.role }}</p><p class="mt-0.5 text-[9px] text-slate-500">{{ role.detail }}</p></div>
                        <NexusBadge :label="role.users" tone="slate" />
                    </div>
                </div>
            </div>
            <div class="p-4"><button class="w-full rounded-lg border border-slate-200 bg-white py-2.5 text-[10px] font-semibold text-slate-700 transition hover:border-[#23c6c8]/50 hover:bg-cyan-50/30 hover:text-[#0e7f82]">Administrar usuarios</button></div>
        </section>
    </div>
</template>
