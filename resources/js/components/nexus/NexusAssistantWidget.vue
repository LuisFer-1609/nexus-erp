<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Maximize2, SquarePen, X } from '@lucide/vue';
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import NexusChat from '@/components/nexus/NexusChat.vue';
import NexusMascotLive from '@/components/nexus/NexusMascotLive.vue';
import { useNexusChat } from '@/composables/useNexusChat';
import { useNexusMood } from '@/composables/useNexusMood';

const open = ref(false);
const chatRef = ref<InstanceType<typeof NexusChat> | null>(null);
const fab = ref<HTMLButtonElement | null>(null);

const { clear, loading } = useNexusChat();
const nexusMood = useNexusMood();

const toggle = async (): Promise<void> => {
    open.value = !open.value;

    if (open.value) {
        nexusMood.set('happy', 1400);
        await nextTick();
        chatRef.value?.focus();
    }
};

const close = (): void => {
    open.value = false;
    fab.value?.focus();
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && open.value) {
        close();
    }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <div class="fixed right-3 bottom-4 z-40 sm:right-6 sm:bottom-6">
        <Transition
            enter-active-class="transition duration-300 ease-[cubic-bezier(0.16,1,0.3,1)] motion-reduce:transition-none"
            enter-from-class="translate-y-4 scale-95 opacity-0 motion-reduce:translate-y-0 motion-reduce:scale-100"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="translate-y-2 scale-95 opacity-0 motion-reduce:translate-y-0 motion-reduce:scale-100"
        >
            <section
                v-if="open"
                id="nexus-assistant-panel"
                role="dialog"
                aria-label="Chat con Nexus"
                class="fixed inset-0 z-50 flex h-[100dvh] w-full flex-col overflow-hidden bg-white sm:absolute sm:inset-auto sm:right-0 sm:bottom-[72px] sm:h-[min(660px,calc(100dvh-7rem))] sm:w-[420px] sm:origin-bottom-right sm:rounded-3xl sm:border sm:border-slate-200/80 sm:shadow-2xl sm:ring-1 sm:shadow-slate-900/20 sm:ring-black/5"
            >
                <header
                    class="relative flex items-center gap-3 overflow-hidden border-b border-slate-100 bg-white px-4 py-3.5 text-slate-900"
                >
                    <NexusMascotLive :zoom="1.12" class="relative size-10" />
                    <div class="relative min-w-0 flex-1">
                        <h2 class="text-sm font-semibold">Nexus</h2>
                    </div>
                    <button
                        type="button"
                        class="relative flex h-10 shrink-0 items-center gap-1.5 rounded-xl px-2.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-[#23c6c8] disabled:opacity-40 motion-reduce:transition-none"
                        aria-label="Nueva conversación"
                        title="Nueva conversación"
                        :disabled="loading"
                        @click="clear"
                    >
                        <SquarePen class="size-4" aria-hidden="true" />
                        <span>Nuevo</span>
                    </button>
                    <Link
                        href="/crm/asistente"
                        class="relative flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transition-none"
                        aria-label="Abrir en pantalla completa"
                        @click="open = false"
                    >
                        <Maximize2 class="size-4" />
                    </Link>
                    <button
                        type="button"
                        class="relative flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transition-none"
                        aria-label="Cerrar chat"
                        @click="close"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="min-h-0 flex-1">
                    <NexusChat ref="chatRef" compact />
                </div>
            </section>
        </Transition>

        <button
            ref="fab"
            type="button"
            class="group relative flex items-center justify-center rounded-full transition duration-300 hover:-translate-y-1 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#23c6c8] active:scale-95 motion-reduce:transform-none motion-reduce:transition-none"
            :class="
                open
                    ? 'hidden size-14 text-slate-700 hover:text-slate-950 sm:flex'
                    : 'size-20'
            "
            :aria-label="
                open ? 'Cerrar chat con Nexus' : 'Abrir chat con Nexus'
            "
            :aria-expanded="open"
            aria-controls="nexus-assistant-panel"
            @click="toggle"
            @pointerenter="nexusMood.set('cute', 1500)"
        >
            <span
                v-if="!open"
                class="absolute inset-2 rounded-full border border-[#23c6c8]/50 [animation-duration:2.6s] motion-safe:animate-ping"
                aria-hidden="true"
            />
            <Transition
                mode="out-in"
                enter-active-class="transition duration-200 motion-reduce:transition-none"
                enter-from-class="rotate-90 scale-50 opacity-0 motion-reduce:rotate-0 motion-reduce:scale-100"
                leave-active-class="transition duration-150 motion-reduce:transition-none"
                leave-to-class="-rotate-90 scale-50 opacity-0 motion-reduce:rotate-0 motion-reduce:scale-100"
            >
                <X v-if="open" key="x" class="relative size-7" />
                <NexusMascotLive v-else key="bot" class="relative size-full" />
            </Transition>
        </button>
    </div>
</template>
