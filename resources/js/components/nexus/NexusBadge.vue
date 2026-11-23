<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        label: string;
        tone?: 'cyan' | 'green' | 'amber' | 'red' | 'slate' | 'blue';
    }>(),
    {
        tone: 'slate',
    },
);

const tones = {
    cyan: 'bg-cyan-50 text-[#139b9e] ring-cyan-100',
    green: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
    amber: 'bg-amber-50 text-amber-700 ring-amber-100',
    red: 'bg-rose-50 text-rose-700 ring-rose-100',
    slate: 'bg-slate-100 text-slate-600 ring-slate-200',
    blue: 'bg-blue-50 text-blue-700 ring-blue-100',
};
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
        :class="tones[props.tone]"
    >
        {{ label }}
    </span>
</template>
