<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        direction?: 'up' | 'down' | 'left' | 'right';
        mode?: 'out-in' | 'in-out' | 'default';
        appear?: boolean;
    }>(),
    {
        direction: 'up',
        mode: 'out-in',
        appear: true,
    },
);

const transitionName = computed(() => `nexus-slide-${props.direction}`);
</script>

<template>
    <Transition
        :name="transitionName"
        :mode="props.mode === 'default' ? undefined : props.mode"
        :appear="props.appear"
    >
        <slot />
    </Transition>
</template>
