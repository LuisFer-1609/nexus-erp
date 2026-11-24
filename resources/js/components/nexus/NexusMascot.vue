<script setup lang="ts">
// Versión estática de la mascota (una sola imagen WebP de ~8 kB) para listas largas, como los
// avatares de cada mensaje. La versión animada es NexusMascotLive.vue.
</script>

<template>
    <span class="flex shrink-0 items-center justify-center" aria-hidden="true">
        <img
            src="/images/nexus/nexus-avatar.webp"
            alt=""
            width="284"
            height="238"
            class="pointer-events-none block h-auto w-[96%] select-none"
            draggable="false"
            decoding="async"
            loading="lazy"
        />
    </span>
</template>
