<script setup lang="ts">
import { Download, X } from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { fileBadge, fileKind } from '@/lib/chatFiles';
import type { ViewerItem } from '@/lib/chatFiles';

const props = defineProps<{ item: ViewerItem | null }>();
const emit = defineEmits<{ close: [] }>();

const closeButton = ref<HTMLButtonElement | null>(null);
const loaded = ref(false);
let previousFocus: HTMLElement | null = null;

// Para PDF: sin la barra oscura del visor del navegador, ajustado al ancho.
const frameSrc = computed<string>(() => {
    if (!props.item) {
        return '';
    }

    return fileKind(props.item.mime, props.item.name) === 'pdf'
        ? `${props.item.url}#toolbar=0&navpanes=0&view=FitH`
        : props.item.url;
});

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

watch(
    () => props.item,
    async (item) => {
        if (item) {
            loaded.value = false;
            previousFocus = document.activeElement as HTMLElement | null;
            document.addEventListener('keydown', onKeydown);
            await nextTick();
            closeButton.value?.focus();

            return;
        }

        document.removeEventListener('keydown', onKeydown);
        previousFocus?.focus();
    },
);

onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0"
        >
            <div
                v-if="item"
                class="fixed inset-0 z-[70] flex flex-col bg-slate-100"
                role="dialog"
                aria-modal="true"
                :aria-label="`Vista previa de ${item.name}`"
            >
                <div
                    class="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 text-slate-900 shadow-sm sm:px-6"
                >
                    <span
                        class="flex h-6 shrink-0 items-center rounded-md px-1.5 text-[11px] font-bold tracking-wide"
                        :class="fileBadge(item.mime, item.name).classes"
                    >
                        {{ fileBadge(item.mime, item.name).label }}
                    </span>
                    <p class="min-w-0 flex-1 truncate text-sm font-semibold">
                        {{ item.name }}
                    </p>
                    <a
                        :href="item.downloadUrl ?? item.url"
                        :download="item.name"
                        class="flex h-10 items-center gap-2 rounded-xl bg-cyan-50 px-3.5 text-sm font-semibold text-[#0f7f82] transition duration-200 hover:bg-cyan-100 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                    >
                        <Download class="size-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Descargar</span>
                    </a>
                    <button
                        ref="closeButton"
                        type="button"
                        class="flex size-10 items-center justify-center rounded-xl text-slate-500 transition duration-200 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                        aria-label="Cerrar vista previa"
                        @click="emit('close')"
                    >
                        <X class="size-5" />
                    </button>
                </div>

                <div
                    class="relative flex min-h-0 flex-1 items-center justify-center p-3 sm:p-6"
                >
                    <!-- Mientras carga el documento se muestra su primera página, sin esperar. -->
                    <img
                        v-if="
                            item.thumb &&
                            !loaded &&
                            fileKind(item.mime, item.name) !== 'image'
                        "
                        :src="item.thumb"
                        alt=""
                        class="absolute inset-0 m-auto max-h-[calc(100%-2rem)] max-w-[min(100%,48rem)] rounded-md object-contain opacity-90 shadow-lg"
                        aria-hidden="true"
                    />
                    <img
                        v-if="fileKind(item.mime, item.name) === 'image'"
                        :src="item.url"
                        :alt="item.name"
                        class="nexus-message max-h-full max-w-full rounded-xl object-contain shadow-xl"
                    />
                    <iframe
                        v-else
                        :src="frameSrc"
                        :title="item.name"
                        class="h-full w-full max-w-4xl rounded-xl bg-white shadow-xl ring-1 ring-slate-200 transition-opacity duration-300"
                        :class="loaded ? 'opacity-100' : 'opacity-0'"
                        @load="loaded = true"
                    />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
