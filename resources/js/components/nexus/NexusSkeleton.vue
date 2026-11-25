<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        className?: string;
        rounded?: 'sm' | 'md' | 'lg' | 'full';
        shimmer?: boolean;
    }>(),
    {
        className: 'h-4 w-full',
        rounded: 'md',
        shimmer: true,
    },
);

const roundedClasses = {
    sm: 'rounded',
    md: 'rounded-md',
    lg: 'rounded-[10px]',
    full: 'rounded-full',
};
</script>

<template>
    <div
        aria-hidden="true"
        :class="[
            shimmer ? 'nexus-skeleton-shimmer' : 'animate-pulse bg-slate-200/80',
            roundedClasses[props.rounded],
            props.className,
        ]"
    />
</template>
