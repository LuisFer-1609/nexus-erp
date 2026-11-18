<script setup lang="ts">
defineProps<{
    eyebrow: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div>
            <p class="mb-2 text-xs font-medium text-slate-500">{{ eyebrow }}</p>
            <h1
                class="text-[30px] font-semibold tracking-tight text-slate-900 md:text-[32px]"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1.5 max-w-3xl text-sm leading-6 text-slate-500"
            >
                {{ description }}
            </p>
        </div>
        <div v-if="$slots.action" class="shrink-0">
            <slot name="action" />
        </div>
    </div>
</template>
