<script setup lang="ts">
import { Check, Copy, Reply, Trash2 } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { formatTime } from '@/lib/chatFiles';

const props = defineProps<{
    text: string;
    createdAt?: string;
    align: 'start' | 'end';
    canReply: boolean;
    canDelete: boolean;
}>();
const emit = defineEmits<{ reply: []; remove: [] }>();

const confirming = ref(false);
const copied = ref(false);
const cancelButton = ref<HTMLButtonElement | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

// Solo se puede copiar texto: un mensaje que es únicamente un archivo no tiene nada que copiar.
const copyable = computed(() => props.text.trim() !== '');

const copy = async (): Promise<void> => {
    try {
        await navigator.clipboard.writeText(props.text);
        copied.value = true;
        clearTimeout(timer);
        timer = setTimeout(() => (copied.value = false), 1600);
    } catch {
        // Sin permiso para el portapapeles: no se hace nada.
    }
};

const remove = (): void => {
    confirming.value = false;
    emit('remove');
};

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        confirming.value = false;
    }
};

// El diálogo flota sobre la conversación: se enfoca «Cancelar» (la acción segura)
// y Escape lo cierra.
watch(confirming, async (open) => {
    if (open) {
        document.addEventListener('keydown', onKeydown);
        await nextTick();
        cancelButton.value?.focus();
    } else {
        document.removeEventListener('keydown', onKeydown);
    }
});

onBeforeUnmount(() => {
    clearTimeout(timer);
    document.removeEventListener('keydown', onKeydown);
});

const buttonClass =
    'flex size-8 items-center justify-center rounded-lg text-slate-400 transition duration-200 hover:bg-slate-200/70 hover:text-slate-700 focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transition-none';
</script>

<template>
    <div class="relative">
        <div
            class="flex items-center gap-1 text-[11px] text-slate-400"
            :class="align === 'end' ? 'flex-row-reverse' : ''"
        >
            <time
                v-if="createdAt"
                class="px-1 tabular-nums"
                :datetime="createdAt"
            >
                {{ formatTime(createdAt) }}
            </time>

            <div
                class="flex items-center gap-0.5 opacity-0 transition duration-200 group-focus-within/msg:opacity-100 group-hover/msg:opacity-100 motion-reduce:transition-none pointer-coarse:opacity-100"
            >
                <button
                    v-if="canReply"
                    type="button"
                    :class="buttonClass"
                    aria-label="Responder a este mensaje"
                    title="Responder"
                    @click="emit('reply')"
                >
                    <Reply class="size-4" />
                </button>
                <button
                    v-if="copyable"
                    type="button"
                    :class="buttonClass"
                    :aria-label="copied ? 'Copiado' : 'Copiar mensaje'"
                    :title="copied ? 'Copiado' : 'Copiar'"
                    @click="copy"
                >
                    <Check v-if="copied" class="size-4 text-emerald-600" />
                    <Copy v-else class="size-4" />
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    :class="[
                        buttonClass,
                        'hover:!bg-rose-50 hover:!text-rose-600',
                    ]"
                    aria-label="Eliminar mensaje"
                    title="Eliminar"
                    @click="confirming = true"
                >
                    <Trash2 class="size-4" />
                </button>
            </div>
        </div>

        <!-- Confirmación flotante, centrada en la pantalla. -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="confirming"
                    class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/30 p-4"
                    @click.self="confirming = false"
                >
                    <div
                        class="nexus-message w-full max-w-sm rounded-[24px] bg-white p-6 text-center shadow-[0_1px_1px_rgba(15,23,42,0.03),0_16px_40px_-14px_rgba(15,23,42,0.28)] ring-1 ring-slate-900/[0.05]"
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="confirm-delete-title"
                        aria-describedby="confirm-delete-text"
                    >
                        <span
                            class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-500"
                        >
                            <Trash2 class="size-6" />
                        </span>
                        <h2
                            id="confirm-delete-title"
                            class="mt-5 text-lg font-semibold tracking-tight text-slate-900"
                        >
                            ¿Eliminar este mensaje?
                        </h2>
                        <p
                            id="confirm-delete-text"
                            class="mt-2 text-sm leading-6 text-slate-500"
                        >
                            Se borrará junto con sus archivos adjuntos. No se
                            puede deshacer.
                        </p>
                        <div class="mt-6 grid grid-cols-2 gap-2.5">
                            <button
                                ref="cancelButton"
                                type="button"
                                class="h-11 rounded-2xl bg-slate-100/80 text-sm font-semibold text-slate-700 transition duration-200 hover:bg-slate-200/70 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                                @click="confirming = false"
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                class="h-11 rounded-2xl bg-rose-500/10 text-sm font-semibold text-rose-600 ring-1 ring-rose-500/15 transition duration-200 hover:bg-rose-600 hover:text-white hover:shadow-[0_10px_24px_-10px_rgba(225,29,72,0.5)] focus-visible:outline-2 focus-visible:outline-rose-600 active:scale-[0.98] motion-reduce:transform-none"
                                @click="remove"
                            >
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
