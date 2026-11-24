<script setup lang="ts">
import { MessageSquare, Plus, Search, Trash2, X } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useNexusChat } from '@/composables/useNexusChat';
import type { ConversationSummary } from '@/composables/useNexusChat';

const emit = defineEmits<{ select: [] }>();

const {
    conversations,
    conversationsLoading,
    activeId,
    loading,
    newConversation,
    openConversation,
    deleteConversation,
    deleteAllConversations,
    loadConversations,
} = useNexusChat();

const query = ref('');
const confirmingId = ref<string | null>(null);
const confirmingAll = ref(false);
const clearingAll = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(query, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => void loadConversations(value), 250);
});

onBeforeUnmount(() => clearTimeout(timer));

const DAY_MS = 86_400_000;
const startOfDay = (date: Date): number =>
    new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime();

type Group = { label: string; items: ConversationSummary[] };

const groups = computed<Group[]>(() => {
    const today = startOfDay(new Date());
    const buckets: Group[] = [
        { label: 'Hoy', items: [] },
        { label: 'Ayer', items: [] },
        { label: 'Últimos 7 días', items: [] },
        { label: 'Anteriores', items: [] },
    ];

    for (const conversation of conversations.value) {
        const day = conversation.last_message_at
            ? startOfDay(new Date(conversation.last_message_at))
            : 0;
        const index =
            day >= today
                ? 0
                : day >= today - DAY_MS
                  ? 1
                  : day >= today - 7 * DAY_MS
                    ? 2
                    : 3;

        buckets[index].items.push(conversation);
    }

    return buckets.filter((bucket) => bucket.items.length > 0);
});

const timeFormat = new Intl.DateTimeFormat('es-MX', {
    hour: 'numeric',
    minute: '2-digit',
});
const dateFormat = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'short',
});

const when = (conversation: ConversationSummary): string => {
    if (!conversation.last_message_at) {
        return '';
    }

    const date = new Date(conversation.last_message_at);

    return startOfDay(date) >= startOfDay(new Date())
        ? timeFormat.format(date)
        : dateFormat.format(date);
};

/** Inicial del título para el avatar de cada conversación. */
const initial = (title: string): string =>
    (title.trim().charAt(0) || '•').toUpperCase();

const open = async (id: string): Promise<void> => {
    confirmingId.value = null;
    await openConversation(id);
    emit('select');
};

const create = (): void => {
    newConversation();
    emit('select');
};

const removeAll = async (): Promise<void> => {
    clearingAll.value = true;
    await deleteAllConversations();
    clearingAll.value = false;
    confirmingAll.value = false;
};

const remove = async (id: string): Promise<void> => {
    confirmingId.value = null;
    await deleteConversation(id);
};
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <div class="space-y-3 p-3.5">
            <button
                type="button"
                class="group flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-white text-sm font-semibold text-[#0f7f82] shadow-[0_1px_2px_rgba(15,23,42,0.06),0_4px_12px_-6px_rgba(15,23,42,0.12)] ring-1 ring-slate-900/[0.05] transition duration-200 hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#23c6c8] active:scale-[0.98] active:bg-slate-100 disabled:opacity-50 motion-reduce:transform-none motion-reduce:transition-none"
                :disabled="loading"
                @click="create"
            >
                <span
                    class="flex size-6 items-center justify-center rounded-lg bg-[#23c6c8]/15 text-[#139b9e] transition duration-300 group-hover:rotate-90 motion-reduce:transform-none"
                >
                    <Plus class="size-4" aria-hidden="true" />
                </span>
                Nueva conversación
            </button>

            <label class="relative block">
                <span class="sr-only">Buscar en tus conversaciones</span>
                <Search
                    class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400"
                    aria-hidden="true"
                />
                <input
                    v-model="query"
                    type="search"
                    maxlength="100"
                    placeholder="Buscar conversaciones…"
                    class="h-10 w-full rounded-2xl border-0 bg-slate-200/50 pr-10 pl-10 text-sm text-slate-900 transition duration-200 outline-none placeholder:text-slate-500 focus:border-[#23c6c8] focus:bg-white focus:shadow-[0_0_0_4px_rgba(35,198,200,0.15)] focus:ring-4 focus:ring-[#23c6c8]/15 [&::-webkit-search-cancel-button]:appearance-none"
                />
                <button
                    v-if="query"
                    type="button"
                    class="absolute top-1/2 right-1.5 flex size-7 -translate-y-1/2 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                    aria-label="Limpiar búsqueda"
                    @click="query = ''"
                >
                    <X class="size-3.5" />
                </button>
            </label>
        </div>

        <nav
            class="min-h-0 flex-1 space-y-5 overflow-y-auto px-2.5 pt-1 pb-4"
            aria-label="Historial de conversaciones"
        >
            <div
                v-if="conversationsLoading && conversations.length === 0"
                class="space-y-2 px-1 pt-1"
                aria-busy="true"
            >
                <div
                    v-for="n in 5"
                    :key="n"
                    class="flex h-16 animate-pulse items-center gap-3 rounded-2xl bg-slate-200/50 px-3"
                >
                    <div class="size-9 rounded-xl bg-slate-200" />
                    <div class="flex-1 space-y-2">
                        <div class="h-2.5 w-3/5 rounded bg-slate-200" />
                        <div class="h-2 w-4/5 rounded bg-slate-200/70" />
                    </div>
                </div>
            </div>

            <div
                v-else-if="conversations.length === 0"
                class="nexus-rise mx-1 mt-6 flex flex-col items-center rounded-3xl border border-dashed border-slate-300/80 bg-white/60 px-5 py-9 text-center"
            >
                <span
                    class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-cyan-50 to-white text-[#139b9e] ring-1 ring-cyan-100"
                >
                    <MessageSquare class="size-5" aria-hidden="true" />
                </span>
                <p class="mt-4 text-sm font-semibold text-slate-800">
                    {{
                        query
                            ? 'Sin resultados'
                            : 'Aún no tienes conversaciones'
                    }}
                </p>
                <p class="mt-1.5 text-xs leading-5 text-slate-500">
                    {{
                        query
                            ? `No encontré nada para «${query}».`
                            : 'Tus conversaciones se guardan aquí para que puedas volver a ellas cuando quieras.'
                    }}
                </p>
            </div>

            <section v-for="group in groups" :key="group.label">
                <h3
                    class="px-2.5 pb-2 text-[11px] font-semibold tracking-[0.12em] text-slate-400 uppercase"
                >
                    {{ group.label }}
                </h3>
                <ul class="space-y-2.5">
                    <li
                        v-for="conversation in group.items"
                        :key="conversation.id"
                        class="group/item relative"
                    >
                        <div
                            v-if="confirmingId === conversation.id"
                            class="nexus-message rounded-[18px] bg-white p-4 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_16px_40px_-14px_rgba(15,23,42,0.28)] ring-1 ring-slate-900/[0.05]"
                            role="alertdialog"
                            aria-label="Confirmar eliminación"
                        >
                            <p
                                class="text-xs leading-5 font-semibold text-slate-900"
                            >
                                ¿Eliminar esta conversación?
                            </p>
                            <p
                                class="mt-0.5 text-[11px] leading-4 text-slate-500"
                            >
                                Se borran también sus archivos. No se puede
                                deshacer.
                            </p>
                            <div class="mt-3 flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="h-9 rounded-xl bg-slate-100/80 px-3.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200/70 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                                    @click="confirmingId = null"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="button"
                                    class="h-9 rounded-xl bg-rose-500/10 px-3.5 text-xs font-semibold text-rose-600 ring-1 ring-rose-500/15 transition hover:bg-rose-600 hover:text-white focus-visible:outline-2 focus-visible:outline-rose-600"
                                    @click="remove(conversation.id)"
                                >
                                    Eliminar
                                </button>
                            </div>
                        </div>

                        <template v-else>
                            <button
                                type="button"
                                class="relative flex w-full items-center gap-3 rounded-[18px] py-3.5 pr-11 pl-3.5 text-left transition duration-300 ease-out hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#23c6c8] motion-reduce:transition-none"
                                :class="
                                    activeId === conversation.id
                                        ? 'bg-white shadow-[0_1px_1px_rgba(15,23,42,0.04),0_12px_30px_-10px_rgba(15,23,42,0.22)] ring-1 ring-slate-900/[0.06]'
                                        : 'bg-white/95 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_6px_18px_-8px_rgba(15,23,42,0.14)] ring-1 ring-slate-900/[0.04] hover:shadow-[0_1px_1px_rgba(15,23,42,0.03),0_14px_28px_-12px_rgba(15,23,42,0.22)]'
                                "
                                :aria-current="
                                    activeId === conversation.id
                                        ? 'true'
                                        : undefined
                                "
                                @click="open(conversation.id)"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-xl text-sm font-bold transition duration-300"
                                    :class="
                                        activeId === conversation.id
                                            ? 'bg-gradient-to-br from-cyan-100 to-cyan-50 text-[#0f7f82] shadow-sm shadow-cyan-900/5'
                                            : 'bg-gradient-to-br from-slate-100 to-slate-50 text-slate-600 shadow-sm shadow-slate-900/5'
                                    "
                                    aria-hidden="true"
                                >
                                    {{ initial(conversation.title) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-baseline justify-between gap-2"
                                    >
                                        <span
                                            class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-900"
                                        >
                                            {{ conversation.title }}
                                        </span>
                                        <span
                                            class="shrink-0 text-[11px] text-slate-400 tabular-nums"
                                        >
                                            {{ when(conversation) }}
                                        </span>
                                    </span>
                                    <span
                                        class="mt-0.5 block truncate text-xs text-slate-500"
                                    >
                                        {{ conversation.preview }}
                                    </span>
                                </span>
                            </button>
                            <button
                                type="button"
                                class="absolute top-1/2 right-2 flex size-8 -translate-y-1/2 items-center justify-center rounded-xl text-slate-400 opacity-0 transition duration-200 group-focus-within/item:opacity-100 group-hover/item:opacity-100 hover:bg-rose-50 hover:text-rose-600 focus-visible:opacity-100 focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transition-none pointer-coarse:opacity-100"
                                :aria-label="`Eliminar la conversación ${conversation.title}`"
                                @click="confirmingId = conversation.id"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </template>
                    </li>
                </ul>
            </section>
        </nav>

        <div v-if="conversations.length > 0" class="px-3.5 pt-1 pb-4">
            <div
                v-if="confirmingAll"
                class="nexus-message rounded-[18px] bg-white p-4 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_6px_18px_-8px_rgba(15,23,42,0.14)] ring-1 ring-slate-900/[0.05]"
                role="alertdialog"
                aria-label="Confirmar borrar todo el historial"
            >
                <p class="text-xs leading-5 font-semibold text-slate-900">
                    ¿Borrar todo el historial?
                </p>
                <p class="mt-0.5 text-[11px] leading-4 text-slate-500">
                    Se borrarán todas tus conversaciones y sus archivos. No se
                    puede deshacer.
                </p>
                <div class="mt-3 flex justify-end gap-2">
                    <button
                        type="button"
                        class="h-9 rounded-xl bg-slate-100/80 px-3.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200/70 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                        @click="confirmingAll = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="h-9 rounded-xl bg-rose-500/10 px-3.5 text-xs font-semibold text-rose-600 ring-1 ring-rose-500/15 transition hover:bg-rose-600 hover:text-white focus-visible:outline-2 focus-visible:outline-rose-600 disabled:opacity-50"
                        :disabled="clearingAll"
                        @click="removeAll"
                    >
                        Borrar todo
                    </button>
                </div>
            </div>
            <button
                v-else
                type="button"
                class="flex h-11 w-full items-center justify-center gap-2 rounded-2xl bg-white text-sm font-medium text-rose-600 shadow-[0_1px_2px_rgba(15,23,42,0.05)] ring-1 ring-slate-900/[0.05] transition duration-200 hover:bg-rose-50 focus-visible:outline-2 focus-visible:outline-rose-600 disabled:opacity-50 motion-reduce:transition-none"
                :disabled="loading"
                @click="confirmingAll = true"
            >
                <Trash2 class="size-4" aria-hidden="true" />
                Borrar todo el historial
            </button>
        </div>
    </div>
</template>
