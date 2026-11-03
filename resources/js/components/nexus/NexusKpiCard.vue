<script setup lang="ts">
import type { Component } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        helper?: string;
        icon?: Component;
        tone?: 'cyan' | 'green' | 'amber' | 'violet';
    }>(),
    {
        helper: '',
        tone: 'cyan',
    },
);

const toneClasses = {
    cyan: {
        icon: 'bg-cyan-50 text-[#139b9e]',
        helper: 'text-[#139b9e]',
        mark: 'bg-[#23c6c8]',
    },
    green: {
        icon: 'bg-emerald-50 text-emerald-600',
        helper: 'text-emerald-600',
        mark: 'bg-emerald-500',
    },
    amber: {
        icon: 'bg-amber-50 text-amber-600',
        helper: 'text-amber-600',
        mark: 'bg-amber-500',
    },
    violet: {
        icon: 'bg-violet-50 text-violet-600',
        helper: 'text-violet-600',
        mark: 'bg-violet-500',
    },
};
</script>

<template>
    <article
        class="group relative min-h-[112px] overflow-hidden rounded-[10px] border border-slate-200 bg-white p-4 transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md hover:shadow-slate-200/50 motion-reduce:transform-none motion-reduce:transition-none"
    >
        <span class="absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity group-hover:opacity-100" :class="toneClasses[props.tone].mark" />
        <div class="flex items-start gap-3.5">
            <div
                v-if="icon"
                class="flex size-[34px] shrink-0 items-center justify-center rounded-lg"
                :class="toneClasses[props.tone].icon"
            >
                <component :is="icon" class="size-4" />
            </div>
            <div class="min-w-0">
                <p class="text-[11px] font-medium text-slate-500">{{ label }}</p>
                <p class="mt-1 text-[23px] font-semibold tracking-tight text-slate-900">{{ value }}</p>
                <p v-if="helper" class="mt-1 text-[10px] font-medium" :class="toneClasses[props.tone].helper">
                    {{ helper }}
                </p>
            </div>
        </div>
    </article>
</template>
