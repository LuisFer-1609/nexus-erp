<script setup lang="ts">
import {
    ArrowUpRight,
    ChartColumn,
    Check,
    FileText,
    Mic,
    Paperclip,
    RotateCcw,
    SendHorizontal,
    ShieldCheck,
    Sparkles,
    X,
} from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import NexusAttachments from '@/components/nexus/NexusAttachments.vue';
import NexusMascot from '@/components/nexus/NexusMascot.vue';
import NexusMascotLive from '@/components/nexus/NexusMascotLive.vue';
import NexusThinking from '@/components/nexus/NexusThinking.vue';
import NexusMessageMeta from '@/components/nexus/NexusMessageMeta.vue';
import NexusQuote from '@/components/nexus/NexusQuote.vue';
import NexusViewer from '@/components/nexus/NexusViewer.vue';
import { Button } from '@/components/ui/button';
import {
    ACCEPTED_FILES,
    MAX_FILES,
    useNexusChat,
    validateFiles,
} from '@/composables/useNexusChat';
import { useDictation } from '@/composables/useDictation';
import { useNexusMood } from '@/composables/useNexusMood';
import type { ChatMessage } from '@/composables/useNexusChat';
import { fileBadge, formatSize } from '@/lib/chatFiles';
import type { ViewerItem } from '@/lib/chatFiles';
import { renderChatMarkdown } from '@/lib/chatMarkdown';

const props = withDefaults(
    defineProps<{ compact?: boolean; floating?: boolean }>(),
    {
        compact: false,
        floating: false,
    },
);

type PendingFile = { key: string; file: File; url: string | null };

const {
    messages,
    loading,
    opening,
    maxLength,
    lastFailed,
    replyTo,
    send,
    retry,
    confirmAction,
    cancelAction,
    startReply,
    cancelReply,
    deleteMessage,
} = useNexusChat();

const draft = ref('');
const pending = ref<PendingFile[]>([]);
const fileError = ref('');
const dragging = ref(false);
const viewer = ref<ViewerItem | null>(null);
const input = ref<HTMLTextAreaElement | null>(null);
const picker = ref<HTMLInputElement | null>(null);
const scroller = ref<HTMLElement | null>(null);

const capabilities = [
    { icon: Sparkles, label: 'Datos al instante' },
    { icon: FileText, label: 'Reportes y pre-nómina' },
    { icon: ChartColumn, label: 'Gráficas en reportes' },
    { icon: ShieldCheck, label: 'Solo tu empresa' },
];
const allSuggestions = [
    '¿Cuántos leads tengo y en qué etapa están?',
    'Hazme un reporte PDF de los leads por etapa con gráfica de barras',
    '¿Quién llegó tarde en los últimos días?',
    '¿Qué hay de nuevo en la plataforma?',
];
const suggestions = props.compact ? allSuggestions.slice(0, 3) : allSuggestions;

const replyPreview = computed(() => {
    const target = replyTo.value;

    if (!target) {
        return '';
    }

    if (target.text.trim() !== '') {
        return target.text.replace(/\s+/g, ' ');
    }

    return (target.attachments ?? []).map((file) => file.name).join(', ');
});

const canSend = (): boolean =>
    !loading.value && (draft.value.trim() !== '' || pending.value.length > 0);

const nexusMood = useNexusMood();

/** Última respuesta de Nexus: solo esa entra con la animación escalonada. */
const latestAssistantId = computed(
    () =>
        [...messages.value]
            .reverse()
            .find((message) => message.role === 'assistant' && !message.error)
            ?.id ?? null,
);

/** Último mensaje del usuario: decide los pasos que muestra la espera. */
const lastUserText = computed(
    () =>
        [...messages.value].reverse().find((message) => message.role === 'user')
            ?.text ?? '',
);

const onInput = (): void => {
    // Escribir con el teclado toma el control del texto: el dictado se detiene.
    if (dictating.value) {
        dictation.stop();
    }

    resize();
    // Mientras el usuario escribe, la mascota escucha y mira hacia el campo de texto.
    nexusMood.typing();
};

const resize = (): void => {
    const element = input.value;

    if (!element) {
        return;
    }

    element.style.height = 'auto';
    element.style.height = `${Math.min(element.scrollHeight, 140)}px`;
};

// Dictado por voz: lo dicho se añade al texto que ya había en el campo.
let dictationBase = '';

const composeDictation = (base: string, spoken: string): string => {
    const startsSentence = base.trim() === '' || /[.!?]\s*$/.test(base);
    const text = startsSentence
        ? spoken.charAt(0).toUpperCase() + spoken.slice(1)
        : spoken;
    const separator = base === '' || /\s$/.test(base) ? '' : ' ';

    return `${base}${separator}${text}`.slice(0, maxLength);
};

const dictation = useDictation({
    onUpdate: (spoken) => {
        draft.value = composeDictation(dictationBase, spoken);
        nexusMood.typing();
        void nextTick(resize);
    },
    onError: (message) => {
        fileError.value = message;
    },
});
const { supported: dictationSupported, listening: dictating } = dictation;

const toggleDictation = (): void => {
    if (dictating.value) {
        dictation.stop();

        return;
    }

    fileError.value = '';
    dictationBase = draft.value;
    dictation.start();
    input.value?.focus();
};

const releasePending = (items: PendingFile[]): void =>
    items.forEach((item) => item.url && URL.revokeObjectURL(item.url));

const addFiles = (incoming: File[]): void => {
    const files = [...pending.value.map((p) => p.file), ...incoming].slice(
        0,
        MAX_FILES + 1,
    );
    const error = validateFiles(files);

    if (error) {
        fileError.value = error;

        return;
    }

    fileError.value = '';
    releasePending(pending.value);
    pending.value = files.map((file, index) => ({
        key: `${file.name}-${file.size}-${index}`,
        file,
        url: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
    }));
};

const onPick = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    addFiles(Array.from(target.files ?? []));
    target.value = '';
};

const onDrop = (event: DragEvent): void => {
    dragging.value = false;
    addFiles(Array.from(event.dataTransfer?.files ?? []));
};

const onPaste = (event: ClipboardEvent): void => {
    const images = Array.from(event.clipboardData?.files ?? []).filter((file) =>
        file.type.startsWith('image/'),
    );

    if (images.length > 0) {
        event.preventDefault();
        addFiles(images);
    }
};

const removeFile = (index: number): void => {
    const removed = pending.value[index];
    pending.value = pending.value.filter((_, i) => i !== index);
    releasePending(removed ? [removed] : []);
    fileError.value = '';
};

const submit = async (text?: string): Promise<void> => {
    const value = (text ?? draft.value).trim();
    const attached = text === undefined ? pending.value.map((p) => p.file) : [];

    if ((value === '' && attached.length === 0) || loading.value) {
        return;
    }

    // Lo que se estaba dictando ya va en el mensaje: se descarta cualquier fragmento pendiente.
    dictation.cancel();

    if (text === undefined) {
        draft.value = '';
        releasePending(pending.value);
        pending.value = [];
        fileError.value = '';
    }

    await nextTick();
    resize();
    await send(value, attached);
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
        event.preventDefault();
        void submit();
    }

    if (event.key === 'Escape' && replyTo.value) {
        cancelReply();
    }
};

const reply = (message: ChatMessage): void => {
    startReply(message);
    input.value?.focus();
};

const prefersReducedMotion = (): boolean =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const jumpTo = (id: number): void => {
    const target = scroller.value?.querySelector<HTMLElement>(
        `[data-msg-id="${id}"]`,
    );

    if (!target) {
        return;
    }

    target.scrollIntoView({
        block: 'center',
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
    });
    target.classList.remove('nexus-flash');
    // Reinicia la animación aunque se pulse varias veces seguidas.
    void target.offsetWidth;
    target.classList.add('nexus-flash');
    setTimeout(() => target.classList.remove('nexus-flash'), 1600);
};

// Un mensaje nuevo se desplaza con suavidad; al abrir una conversación larga
// (distancia mayor a dos pantallas) se salta al final para no recorrerla entera.
const scrollToEnd = async (): Promise<void> => {
    await nextTick();

    const el = scroller.value;

    if (!el) {
        return;
    }

    // Sin mensajes solo está la bienvenida: se muestra completa, desde arriba.
    if (messages.value.length === 0) {
        el.scrollTop = 0;

        return;
    }

    const distance = el.scrollHeight - el.clientHeight - el.scrollTop;
    const instant = prefersReducedMotion() || distance > el.clientHeight * 2;

    el.scrollTo({
        top: el.scrollHeight,
        behavior: instant ? 'auto' : 'smooth',
    });
};

watch(() => [messages.value.length, loading.value, opening.value], scrollToEnd);
onMounted(scrollToEnd);
onBeforeUnmount(() => releasePending(pending.value));

defineExpose({ focus: (): void => input.value?.focus() });
</script>

<template>
    <div
        class="relative flex h-full min-h-0 flex-col"
        :class="
            floating
                ? ''
                : 'bg-slate-50/60 bg-[radial-gradient(circle,#cbd5e1_0.6px,transparent_0.6px)] [background-size:20px_20px]'
        "
        @dragover.prevent="dragging = true"
        @dragleave.self="dragging = false"
        @drop.prevent="onDrop"
    >
        <Transition
            enter-active-class="transition duration-200 motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-200 motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="dragging"
                class="pointer-events-none absolute inset-3 z-10 flex items-center justify-center rounded-2xl border-2 border-dashed border-[#23c6c8] bg-white/95 text-sm font-semibold text-[#139b9e] shadow-xl shadow-cyan-900/10"
            >
                <span class="flex items-center gap-2">
                    <Paperclip class="size-4" />
                    Suelta tus documentos aquí
                </span>
            </div>
        </Transition>

        <div
            ref="scroller"
            class="overflow-y-auto overscroll-contain px-4 sm:px-8"
            :class="
                floating
                    ? 'absolute inset-0 pt-24 pb-44'
                    : compact
                      ? 'absolute inset-0 pt-3 pb-28'
                      : 'flex-1 py-6'
            "
            role="log"
            aria-live="polite"
            aria-label="Conversación con Nexus"
        >
            <div
                class="mx-auto flex min-h-full w-full flex-col"
                :class="compact ? '' : 'max-w-5xl'"
            >
                <div
                    v-if="opening && messages.length === 0"
                    class="space-y-6"
                    aria-busy="true"
                    aria-label="Cargando conversación"
                >
                    <div class="flex justify-end">
                        <div
                            class="h-10 w-2/5 animate-pulse rounded-2xl bg-slate-200/80"
                        />
                    </div>
                    <div class="flex gap-3">
                        <div
                            class="size-9 shrink-0 animate-pulse rounded-xl bg-slate-200/80"
                        />
                        <div
                            class="h-24 w-3/5 animate-pulse rounded-2xl bg-slate-200/80"
                        />
                    </div>
                    <div class="flex justify-end">
                        <div
                            class="h-10 w-1/3 animate-pulse rounded-2xl bg-slate-200/80"
                        />
                    </div>
                </div>

                <div
                    v-else-if="messages.length === 0"
                    class="mx-auto flex flex-1 flex-col items-center px-2 text-center"
                    :class="
                        compact
                            ? 'min-h-full max-w-lg justify-center'
                            : 'max-w-2xl justify-center'
                    "
                >
                    <div
                        class="relative flex flex-col items-center"
                        aria-hidden="true"
                    >
                        <span
                            class="pointer-events-none absolute top-1/2 left-1/2 size-56 -translate-x-1/2 -translate-y-[58%] rounded-full bg-[radial-gradient(closest-side,rgba(207,250,254,0.9),rgba(207,250,254,0))]"
                        />
                        <NexusMascotLive
                            variant="full"
                            :class="[
                                'relative cursor-pointer',
                                compact ? 'w-20' : 'w-32',
                            ]"
                            @click="nexusMood.poke()"
                        />
                        <span
                            class="nexus-shadow mt-1 h-2.5 rounded-[50%] bg-[radial-gradient(closest-side,rgba(10,22,30,0.28),rgba(10,22,30,0))]"
                            :class="compact ? 'w-20' : 'w-24'"
                        />
                    </div>

                    <h2
                        class="nexus-rise font-semibold tracking-tight text-slate-900"
                        :class="compact ? 'mt-3 text-2xl' : 'mt-5 text-[28px]'"
                        style="animation-delay: 120ms"
                    >
                        Hola, soy <span class="text-[#139b9e]">Nexus</span>
                    </h2>
                    <p
                        v-if="!compact"
                        class="nexus-rise mt-2.5 text-sm leading-6 text-slate-500"
                        style="animation-delay: 200ms"
                    >
                        Consulto los datos de tu empresa, genero reportes en
                        PDF, preparo la pre-nómina y te cuento qué hay de nuevo.
                        Solo respondo con información real y actualizada.
                    </p>

                    <ul
                        v-if="!compact"
                        class="nexus-rise mt-4 flex flex-wrap justify-center gap-2"
                        style="animation-delay: 240ms"
                    >
                        <li
                            v-for="chip in capabilities"
                            :key="chip.label"
                            class="flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-slate-700 shadow-[0_1px_2px_rgba(15,23,42,0.05),0_6px_14px_-8px_rgba(15,23,42,0.14)] ring-1 ring-slate-900/[0.04]"
                        >
                            <component
                                :is="chip.icon"
                                class="size-3.5"
                                aria-hidden="true"
                            />
                            {{ chip.label }}
                        </li>
                    </ul>

                    <div
                        class="grid w-full gap-2.5"
                        :class="compact ? 'mt-4' : 'mt-7 sm:grid-cols-2'"
                    >
                        <button
                            v-for="(suggestion, index) in suggestions"
                            :key="suggestion"
                            type="button"
                            class="nexus-rise group flex items-center justify-between gap-3 rounded-[18px] bg-white/95 px-4 text-left text-[13.5px] leading-5 text-slate-700 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_6px_18px_-8px_rgba(15,23,42,0.14)] ring-1 ring-slate-900/[0.04] transition duration-300 hover:-translate-y-0.5 hover:text-slate-900 hover:shadow-[0_1px_1px_rgba(15,23,42,0.03),0_14px_28px_-12px_rgba(15,23,42,0.22)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#23c6c8] motion-reduce:transform-none motion-reduce:transition-none"
                            :style="{ animationDelay: `${280 + index * 90}ms` }"
                            :class="compact ? 'py-3' : 'py-4'"
                            @click="submit(suggestion)"
                        >
                            <span>{{ suggestion }}</span>
                            <ArrowUpRight
                                class="size-3.5 shrink-0 text-slate-500 transition duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-[#139b9e]"
                                aria-hidden="true"
                            />
                        </button>
                    </div>

                    <p
                        class="flex items-center justify-center gap-1.5 text-[11px] text-slate-400"
                        :class="compact ? 'mt-3' : 'mt-6'"
                    >
                        <span
                            class="size-1 rounded-full bg-[#23c6c8]"
                            aria-hidden="true"
                        />
                        Nexus responde con los datos de tu empresa y puede
                        equivocarse: verifica lo importante.
                    </p>
                </div>

                <TransitionGroup
                    enter-active-class="nexus-message motion-reduce:animate-none"
                    leave-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                    leave-to-class="opacity-0 -translate-x-2"
                    tag="div"
                    class="space-y-1.5"
                >
                    <template v-for="message in messages" :key="message.id">
                        <div
                            v-if="message.role === 'user'"
                            :data-msg-id="message.id"
                            class="group/msg -mx-2 flex flex-col items-end gap-0 rounded-2xl px-2 py-0"
                        >
                            <div
                                class="w-fit max-w-[85%] overflow-hidden rounded-[18px] rounded-tr-md bg-gradient-to-b from-white to-cyan-50/70 p-1 text-slate-800 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_6px_18px_-8px_rgba(15,23,42,0.14)] ring-1 ring-cyan-900/[0.06]"
                            >
                                <NexusQuote
                                    v-if="message.replyTo"
                                    :reply="message.replyTo"
                                    own
                                    @jump="jumpTo"
                                />
                                <NexusAttachments
                                    v-if="message.attachments?.length"
                                    :attachments="message.attachments"
                                    own
                                    @open="viewer = $event"
                                />
                                <p
                                    v-if="message.text"
                                    class="px-3 py-1.5 text-sm leading-6 break-words whitespace-pre-wrap"
                                >
                                    {{ message.text }}
                                </p>
                            </div>
                            <NexusMessageMeta
                                :text="message.text"
                                :created-at="message.createdAt"
                                align="end"
                                :can-reply="message.id > 0"
                                :can-delete="!loading"
                                @reply="reply(message)"
                                @remove="deleteMessage(message.id)"
                            />
                        </div>

                        <div
                            v-else
                            :data-msg-id="message.id"
                            class="group/msg -mx-2 flex items-start gap-3 rounded-2xl px-2 py-0"
                        >
                            <NexusMascot class="size-9" />
                            <div class="max-w-[88%] min-w-0 space-y-2.5">
                                <div
                                    class="w-fit max-w-full rounded-[18px] rounded-tl-md p-1 text-sm leading-6 break-words shadow-[0_1px_1px_rgba(15,23,42,0.03),0_6px_18px_-8px_rgba(15,23,42,0.14)] ring-1"
                                    :class="
                                        message.error
                                            ? 'bg-rose-50 text-rose-700 ring-rose-200'
                                            : 'bg-white text-slate-700 ring-slate-900/[0.04]'
                                    "
                                >
                                    <NexusQuote
                                        v-if="message.replyTo"
                                        :reply="message.replyTo"
                                        @jump="jumpTo"
                                    />
                                    <NexusAttachments
                                        v-if="message.artifacts?.length"
                                        :artifacts="message.artifacts"
                                        class="mb-1"
                                        @open="viewer = $event"
                                    />
                                    <div
                                        class="px-3 py-2 [&_code]:rounded [&_code]:bg-slate-100 [&_code]:px-1 [&_li]:mt-0.5 [&_p+p]:mt-2 [&_p+ul]:mt-2 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5 [&_ul+p]:mt-2"
                                    >
                                        <span
                                            v-if="message.error"
                                            class="sr-only"
                                            >Error:
                                        </span>
                                        <div
                                            :class="{
                                                'nexus-reveal-body':
                                                    message.id ===
                                                    latestAssistantId,
                                            }"
                                            v-html="
                                                renderChatMarkdown(message.text)
                                            "
                                        />
                                    </div>
                                </div>

                                <div
                                    v-for="action in message.actions"
                                    :key="action.id"
                                    class="rounded-2xl border border-cyan-200/70 bg-gradient-to-br from-cyan-50 via-white to-white p-4 shadow-sm"
                                >
                                    <p
                                        class="text-sm font-semibold text-slate-900"
                                    >
                                        {{ action.title }}
                                    </p>
                                    <ul
                                        class="mt-1.5 space-y-0.5 text-xs text-slate-600"
                                    >
                                        <li
                                            v-for="line in action.lines"
                                            :key="line"
                                        >
                                            {{ line }}
                                        </li>
                                    </ul>

                                    <p
                                        v-if="action.note"
                                        class="mt-2 text-xs font-medium text-rose-700"
                                        role="alert"
                                    >
                                        {{ action.note }}
                                    </p>

                                    <p
                                        v-if="action.status === 'done'"
                                        class="mt-3 flex items-center gap-1.5 text-xs font-semibold text-emerald-700"
                                    >
                                        <Check class="size-4" /> Confirmada
                                    </p>
                                    <p
                                        v-else-if="
                                            action.status === 'cancelled'
                                        "
                                        class="mt-3 text-xs font-semibold text-slate-500"
                                    >
                                        Cancelada
                                    </p>
                                    <div
                                        v-else
                                        class="mt-3.5 flex flex-wrap gap-2"
                                    >
                                        <Button
                                            size="sm"
                                            class="rounded-xl bg-[#0a161e] text-[#23c6c8] shadow-md shadow-slate-900/15 transition duration-200 hover:-translate-y-px hover:bg-[#162732] active:scale-[0.98]"
                                            :disabled="
                                                action.status === 'working'
                                            "
                                            @click="
                                                confirmAction(
                                                    message.id,
                                                    action.id,
                                                )
                                            "
                                        >
                                            {{
                                                action.status === 'working'
                                                    ? 'Generando…'
                                                    : action.confirmLabel
                                            }}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            class="rounded-xl transition duration-200 hover:-translate-y-px"
                                            :disabled="
                                                action.status === 'working'
                                            "
                                            @click="
                                                cancelAction(
                                                    message.id,
                                                    action.id,
                                                )
                                            "
                                        >
                                            Cancelar
                                        </Button>
                                    </div>
                                </div>

                                <button
                                    v-if="
                                        message.error &&
                                        lastFailed &&
                                        message.id ===
                                            messages[messages.length - 1]?.id
                                    "
                                    type="button"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold text-[#139b9e] transition duration-200 hover:bg-cyan-50 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                                    @click="retry"
                                >
                                    <RotateCcw class="size-3.5" />
                                    Reintentar
                                </button>

                                <NexusMessageMeta
                                    :text="message.text"
                                    :created-at="message.createdAt"
                                    align="start"
                                    :can-reply="
                                        message.id > 0 && !message.error
                                    "
                                    :can-delete="!loading"
                                    @reply="reply(message)"
                                    @remove="deleteMessage(message.id)"
                                />
                            </div>
                        </div>
                    </template>
                </TransitionGroup>

                <div
                    v-if="loading"
                    class="nexus-message mt-5 flex items-start gap-3"
                >
                    <NexusMascotLive :zoom="1.12" class="size-9" />
                    <NexusThinking :request="lastUserText" />
                </div>
            </div>
        </div>

        <form
            class="px-3 pb-3 sm:px-6 sm:pb-5"
            :class="
                floating
                    ? 'absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-slate-50 via-slate-50/85 to-transparent pt-24'
                    : compact
                      ? 'absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-white via-white/90 to-transparent pt-8'
                      : 'bg-gradient-to-t from-white via-white/80 to-transparent pt-24'
            "
            @submit.prevent="submit()"
        >
            <div class="mx-auto w-full" :class="compact ? '' : 'max-w-5xl'">
                <Transition
                    enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                    enter-from-class="translate-y-2 opacity-0"
                    leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="replyTo"
                        class="mx-1 mb-3 flex items-center gap-3 rounded-[20px] bg-white py-3 pr-2.5 pl-4 shadow-[0_1px_1px_rgba(15,23,42,0.03),0_10px_28px_-12px_rgba(15,23,42,0.2)] ring-1 ring-slate-900/[0.05]"
                    >
                        <span
                            class="h-9 w-[3px] shrink-0 rounded-full bg-gradient-to-b from-[#23c6c8] to-[#139b9e]"
                            aria-hidden="true"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-[11px] font-semibold tracking-wide text-[#139b9e] uppercase"
                            >
                                Respondiendo a
                                {{
                                    replyTo.role === 'assistant'
                                        ? 'Nexus'
                                        : 'ti'
                                }}
                            </p>
                            <p class="truncate text-xs text-slate-600">
                                {{ replyPreview }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="flex size-8 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                            aria-label="Cancelar respuesta"
                            @click="cancelReply"
                        >
                            <X class="size-4" />
                        </button>
                    </div>
                </Transition>

                <TransitionGroup
                    v-if="pending.length"
                    tag="ul"
                    enter-active-class="nexus-message motion-reduce:animate-none"
                    leave-active-class="transition duration-150 motion-reduce:transition-none"
                    leave-to-class="scale-90 opacity-0"
                    class="mb-2.5 flex flex-wrap gap-2"
                >
                    <li
                        v-for="(item, index) in pending"
                        :key="item.key"
                        class="relative"
                    >
                        <div
                            v-if="item.url"
                            class="size-16 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-slate-200"
                        >
                            <img
                                :src="item.url"
                                :alt="item.file.name"
                                class="size-full object-cover"
                            />
                        </div>
                        <div
                            v-else
                            class="flex h-16 max-w-[230px] items-center gap-2.5 rounded-xl bg-slate-50 py-2 pr-9 pl-2.5 ring-1 ring-slate-200"
                        >
                            <span
                                class="flex h-9 w-8 shrink-0 items-center justify-center rounded-md text-[10px] font-bold tracking-wide"
                                :class="
                                    fileBadge(item.file.type, item.file.name)
                                        .classes
                                "
                            >
                                {{
                                    fileBadge(item.file.type, item.file.name)
                                        .label
                                }}
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-xs font-medium text-slate-800"
                                    >{{ item.file.name }}</span
                                >
                                <span
                                    class="block text-[11px] text-slate-500"
                                    >{{ formatSize(item.file.size) }}</span
                                >
                            </span>
                        </div>
                        <button
                            type="button"
                            class="absolute -top-1.5 -right-1.5 flex size-6 items-center justify-center rounded-full bg-slate-800 text-white shadow-md transition hover:bg-slate-950 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                            :aria-label="`Quitar ${item.file.name}`"
                            @click="removeFile(index)"
                        >
                            <X class="size-3.5" />
                        </button>
                    </li>
                </TransitionGroup>
                <p
                    v-if="fileError"
                    class="mb-2 text-xs font-medium text-rose-700"
                    role="alert"
                >
                    {{ fileError }}
                </p>
                <span class="sr-only" role="status">{{
                    dictating ? 'Dictado activado. Habla ahora.' : ''
                }}</span>

                <div
                    class="flex items-end gap-1 rounded-[28px] border border-slate-200 bg-white p-2 shadow-[0_1px_2px_rgba(15,23,42,0.05),0_14px_34px_-14px_rgba(15,23,42,0.2)] ring-4 transition duration-300 motion-reduce:transition-none"
                    :class="dictating ? 'ring-rose-100' : 'ring-transparent'"
                >
                    <input
                        ref="picker"
                        type="file"
                        class="hidden"
                        multiple
                        :accept="ACCEPTED_FILES"
                        @change="onPick"
                    />
                    <button
                        type="button"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full text-slate-400 transition duration-200 hover:bg-slate-100 hover:text-[#139b9e] focus-visible:outline-2 focus-visible:outline-[#23c6c8] disabled:opacity-40"
                        aria-label="Adjuntar documento"
                        :disabled="loading"
                        @click="picker?.click()"
                    >
                        <Paperclip class="size-[18px]" />
                    </button>
                    <textarea
                        ref="input"
                        v-model="draft"
                        rows="1"
                        :maxlength="maxLength"
                        :placeholder="
                            dictating
                                ? 'Escuchando… habla ahora'
                                : 'Pregúntale algo a Nexus…'
                        "
                        aria-label="Mensaje para Nexus"
                        class="max-h-36 min-h-10 flex-1 resize-none bg-transparent px-2 py-2.5 text-[15px] leading-6 text-slate-900 outline-none placeholder:text-slate-400"
                        @input="onInput"
                        @keydown="onKeydown"
                        @paste="onPaste"
                    />
                    <button
                        v-if="dictationSupported"
                        type="button"
                        class="relative flex size-10 shrink-0 items-center justify-center rounded-full transition duration-200 focus-visible:outline-2 focus-visible:outline-[#23c6c8] disabled:opacity-40 motion-reduce:transition-none"
                        :class="
                            dictating
                                ? 'bg-rose-50 text-rose-600'
                                : 'text-slate-400 hover:bg-slate-100 hover:text-[#139b9e]'
                        "
                        :aria-label="
                            dictating
                                ? 'Detener dictado'
                                : 'Dictar mensaje con la voz'
                        "
                        :aria-pressed="dictating"
                        :title="
                            dictating ? 'Detener dictado' : 'Dictar con la voz'
                        "
                        :disabled="loading"
                        @click="toggleDictation"
                    >
                        <span
                            v-if="dictating"
                            class="absolute inset-1 rounded-full bg-rose-400/25 motion-safe:animate-ping"
                            aria-hidden="true"
                        />
                        <span
                            v-if="dictating"
                            class="relative flex h-4 items-center gap-[3px]"
                            aria-hidden="true"
                        >
                            <span
                                v-for="n in 4"
                                :key="n"
                                class="nexus-voice-bar"
                            />
                        </span>
                        <Mic v-else class="size-[18px]" />
                    </button>
                    <Button
                        type="submit"
                        size="icon"
                        class="size-10 shrink-0 rounded-full bg-gradient-to-br from-[#139b9e] to-[#23c6c8] text-white shadow-md shadow-cyan-900/20 transition duration-200 hover:scale-105 hover:shadow-lg active:scale-95 disabled:bg-slate-100 disabled:bg-none disabled:text-slate-400 disabled:shadow-none motion-reduce:transform-none"
                        :disabled="!canSend()"
                        aria-label="Enviar mensaje"
                    >
                        <SendHorizontal class="size-4" />
                    </Button>
                </div>
                <p
                    v-if="draft.length > maxLength * 0.8"
                    class="mt-2 px-1 text-right text-xs text-slate-500 tabular-nums"
                >
                    {{ draft.length }}/{{ maxLength }}
                </p>
            </div>
        </form>

        <NexusViewer :item="viewer" @close="viewer = null" />
    </div>
</template>
