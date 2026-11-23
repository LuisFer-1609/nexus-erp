<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-5 py-4"
            >
                <div>
                    <p class="text-lg font-semibold">NexusERP</p>
                    <p class="text-xs text-slate-500">
                        Administración de la plataforma
                    </p>
                </div>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >Cerrar sesión</Link
                >
            </div>
        </header>
        <main class="mx-auto max-w-6xl px-5 py-8">
            <slot />
        </main>
    </div>
</template>
