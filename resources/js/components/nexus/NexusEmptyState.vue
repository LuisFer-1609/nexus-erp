<script setup lang="ts">
import { Inbox } from '@lucide/vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
    }>(),
    {
        title: 'Sin datos disponibles',
        description:
            'Esta vista está lista para mostrar información real cuando esté disponible.',
    },
);
</script>

<template>
    <section
        class="flex min-h-[420px] items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white/60 px-6 py-16 text-center"
    >
        <div class="max-w-md">
            <div
                class="mx-auto flex size-12 items-center justify-center rounded-full bg-slate-100 text-slate-500"
            >
                <Inbox class="size-5" />
            </div>
            <h2 class="mt-5 text-lg font-semibold text-slate-900">
                {{ title }}
            </h2>
            <p class="mt-2 text-sm leading-6 text-slate-500">
                {{ description }}
            </p>
        </div>
    </section>
</template>
