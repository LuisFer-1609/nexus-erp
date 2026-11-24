<script setup lang="ts">
import type { Component } from 'vue';
import { ref, watch } from 'vue';
import NexusViewTransition from '@/components/nexus/NexusViewTransition.vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        helper?: string;
        icon?: Component;
        tone?: 'cyan' | 'green' | 'amber' | 'red' | 'violet';
    }>(),
    {
        helper: '',
        tone: 'cyan',
    },
);

const valueDirection = ref<'up' | 'down'>('up');

const parseNumeric = (val: string | number): number => {
    if (typeof val === 'number') {
        return val;
    }
    const cleaned = val.replace(/[^0-9.-]+/g, '');
    return Number.parseFloat(cleaned) || 0;
};

watch(
    () => props.value,
    (newVal, oldVal) => {
        const next = parseNumeric(newVal);
        const prev = parseNumeric(oldVal);
        valueDirection.value = next >= prev ? 'up' : 'down';
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
    red: {
        icon: 'bg-rose-50 text-rose-500',
        helper: 'text-rose-500',
        mark: 'bg-rose-500',
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
        class="group relative min-h-[116px] overflow-hidden rounded-[12px] border border-slate-200/90 bg-white p-5 transition duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md hover:shadow-slate-200/50 motion-reduce:transform-none motion-reduce:transition-none"
    >
        <span
            class="absolute inset-x-0 top-0 h-0.5 opacity-0 transition-opacity group-hover:opacity-100"
            :class="toneClasses[props.tone].mark"
        />
        <div class="flex items-start gap-3.5">
            <div
                class="mt-0.5 flex size-[34px] shrink-0 items-center justify-center rounded-full transition-transform duration-200 group-hover:scale-105"
                :class="toneClasses[props.tone].icon"
            >
                <component :is="icon" v-if="icon" class="size-4" />
                <span
                    v-else
                    class="block h-[2px] w-2.5 rounded-full bg-current"
                />
            </div>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-medium text-slate-500">
                    {{ label }}
                </p>
                <div class="mt-1 overflow-hidden">
                    <NexusViewTransition
                        :direction="valueDirection"
                        mode="out-in"
                    >
                        <p
                            :key="String(value)"
                            class="text-[24px] leading-tight font-bold tracking-tight text-slate-900"
                        >
                            {{ value }}
                        </p>
                    </NexusViewTransition>
                </div>
                <p
                    v-if="helper"
                    class="mt-1.5 text-[10px] font-medium"
                    :class="toneClasses[props.tone].helper"
                >
                    {{ helper }}
                </p>
            </div>
        </div>
    </article>
</template>
