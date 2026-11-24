<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    History,
    PanelLeftClose,
    PanelLeftOpen,
    SquarePen,
    X,
} from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import NexusChat from '@/components/nexus/NexusChat.vue';
import NexusMascotLive from '@/components/nexus/NexusMascotLive.vue';
import NexusConversationList from '@/components/nexus/NexusConversationList.vue';
import { useNexusChat } from '@/composables/useNexusChat';

const { hasMessages, loading, newConversation } = useNexusChat();

const HISTORY_KEY = 'nexus-history-open';

const readPreference = (): boolean => {
    try {
        return localStorage.getItem(HISTORY_KEY) !== 'false';
    } catch {
        return true;
    }
};

const historyOpen = ref(readPreference());
const drawerOpen = ref(false);

watch(historyOpen, (open) => {
    try {
        localStorage.setItem(HISTORY_KEY, String(open));
    } catch {
        // La preferencia solo se pierde al recargar.
    }
});

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape' && drawerOpen.value) {
        drawerOpen.value = false;
    }
};

onMounted(() => document.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Head title="Asistente IA" />

    <section
        class="nexus-rise relative flex h-full min-h-0 overflow-hidden bg-gradient-to-br bg-[radial-gradient(circle,#cbd5e1_0.5px,transparent_0.5px)] from-white via-slate-50 to-slate-100/80 [background-size:22px_22px] [background-position:center]"
        aria-label="Nexus Assistant"
    >
        <aside
            class="hidden shrink-0 overflow-hidden transition-[width] duration-300 ease-out motion-reduce:transition-none lg:block"
            :class="historyOpen ? 'w-[316px]' : 'w-0'"
            aria-label="Historial de conversaciones"
        >
            <div class="h-full w-[316px] p-3">
                <div class="flex h-full flex-col overflow-hidden">
                    <NexusConversationList />
                </div>
            </div>
        </aside>

        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="drawerOpen"
                class="absolute inset-0 z-30 bg-slate-900/30 lg:hidden"
                @click.self="drawerOpen = false"
            >
                <div
                    class="nexus-message m-3 flex h-[calc(100%-1.5rem)] w-[min(320px,86vw)] flex-col overflow-hidden rounded-3xl border border-slate-200/70 bg-white shadow-2xl shadow-slate-900/20"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Historial de conversaciones"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200 px-4 py-3"
                    >
                        <h2 class="text-sm font-semibold text-slate-900">
                            Conversaciones
                        </h2>
                        <button
                            type="button"
                            class="flex size-10 items-center justify-center rounded-xl text-slate-500 transition hover:bg-slate-200/70 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                            aria-label="Cerrar historial"
                            @click="drawerOpen = false"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                    <div class="min-h-0 flex-1">
                        <NexusConversationList @select="drawerOpen = false" />
                    </div>
                </div>
            </div>
        </Transition>

        <div class="relative flex min-w-0 flex-1 flex-col">
            <div
                class="pointer-events-none absolute -top-24 -right-16 size-72 rounded-full bg-[radial-gradient(closest-side,rgba(207,250,254,0.85),rgba(207,250,254,0))]"
                aria-hidden="true"
            />

            <header
                class="pointer-events-none absolute inset-x-0 top-0 z-20 flex items-center gap-2.5 px-3 pt-3 sm:px-5"
            >
                <button
                    type="button"
                    class="pointer-events-auto hidden size-10 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-600 shadow-[0_1px_2px_rgba(15,23,42,0.05),0_6px_16px_-8px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/[0.05] transition duration-300 hover:-translate-y-0.5 hover:text-slate-900 hover:shadow-[0_1px_2px_rgba(15,23,42,0.05),0_12px_24px_-10px_rgba(15,23,42,0.22)] focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transform-none motion-reduce:transition-none lg:flex"
                    :aria-label="
                        historyOpen ? 'Ocultar historial' : 'Mostrar historial'
                    "
                    :aria-expanded="historyOpen"
                    @click="historyOpen = !historyOpen"
                >
                    <PanelLeftClose v-if="historyOpen" class="size-5" />
                    <PanelLeftOpen v-else class="size-5" />
                </button>
                <button
                    type="button"
                    class="pointer-events-auto flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white text-slate-600 shadow-[0_1px_2px_rgba(15,23,42,0.05),0_6px_16px_-8px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/[0.05] transition duration-300 hover:-translate-y-0.5 hover:text-slate-900 hover:shadow-[0_1px_2px_rgba(15,23,42,0.05),0_12px_24px_-10px_rgba(15,23,42,0.22)] focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transform-none motion-reduce:transition-none lg:hidden"
                    aria-label="Abrir historial de conversaciones"
                    @click="drawerOpen = true"
                >
                    <History class="size-5" />
                </button>

                <div
                    class="pointer-events-auto flex h-11 min-w-0 items-center gap-2.5 rounded-2xl bg-white pr-4 shadow-[0_1px_2px_rgba(15,23,42,0.05),0_6px_16px_-8px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/[0.05]"
                    :class="hasMessages ? 'pl-1.5' : 'pl-4'"
                >
                    <NexusMascotLive
                        v-if="hasMessages"
                        :zoom="1.12"
                        class="size-9"
                    />
                    <h1 class="truncate text-sm font-semibold text-slate-900">
                        Nexus
                    </h1>
                </div>

                <button
                    type="button"
                    class="pointer-events-auto ml-auto flex h-11 shrink-0 items-center gap-2 rounded-2xl bg-white pr-4 pl-2 text-sm font-semibold text-[#0f7f82] shadow-[0_1px_2px_rgba(15,23,42,0.05),0_6px_16px_-8px_rgba(15,23,42,0.16)] ring-1 ring-slate-900/[0.05] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_1px_2px_rgba(15,23,42,0.05),0_12px_24px_-10px_rgba(15,23,42,0.22)] focus-visible:outline-2 focus-visible:outline-[#23c6c8] disabled:opacity-40 motion-reduce:transform-none motion-reduce:transition-none"
                    aria-label="Nueva conversación"
                    :disabled="loading"
                    @click="newConversation"
                >
                    <span
                        class="flex size-7 items-center justify-center rounded-xl bg-[#23c6c8]/15 text-[#139b9e]"
                    >
                        <SquarePen class="size-4" aria-hidden="true" />
                    </span>
                    <span class="hidden sm:inline">Nueva conversación</span>
                </button>
            </header>

            <NexusChat floating class="min-h-0 flex-1" />
        </div>
    </section>
</template>
