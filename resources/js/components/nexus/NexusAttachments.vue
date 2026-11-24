<script setup lang="ts">
import { Download, Expand, FileText } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ChatArtifact, ChatAttachment } from '@/composables/useNexusChat';
import { fileKind, formatSize } from '@/lib/chatFiles';
import type { ViewerItem } from '@/lib/chatFiles';

const props = withDefaults(
    defineProps<{
        attachments?: ChatAttachment[];
        artifacts?: ChatArtifact[];
        /** Dentro de la burbuja oscura del usuario. */
        own?: boolean;
    }>(),
    { attachments: () => [], artifacts: () => [], own: false },
);

const emit = defineEmits<{ open: [item: ViewerItem] }>();

type Tone = { header: string; icon: string; label: string };

const TONES: Record<string, Tone> = {
    pdf: {
        header: 'from-rose-50 to-rose-100',
        icon: 'text-rose-600',
        label: 'PDF',
    },
    sheet: {
        header: 'from-emerald-50 to-emerald-100',
        icon: 'text-emerald-600',
        label: 'CSV',
    },
    text: {
        header: 'from-slate-50 to-slate-100',
        icon: 'text-slate-600',
        label: 'TXT',
    },
    report: {
        header: 'from-cyan-50 to-slate-100',
        icon: 'text-[#139b9e]',
        label: 'REPORTE',
    },
};

type Card = {
    key: string;
    name: string;
    size: number;
    tone: Tone;
    detail: string;
    viewer: ViewerItem;
    download: string;
    thumb: string | null;
};

// Miniaturas que no pudieron cargarse (por ejemplo, si el servidor no tiene pdftoppm):
// en ese caso la tarjeta muestra su diseño de respaldo.
const broken = ref<ReadonlySet<string>>(new Set());
const markBroken = (key: string): void => {
    broken.value = new Set([...broken.value, key]);
};
const hasThumb = (card: Card): boolean =>
    card.thumb !== null && !broken.value.has(card.key);

const images = computed(() =>
    props.attachments.filter(
        (file) => fileKind(file.mime, file.name) === 'image',
    ),
);

const cards = computed<Card[]>(() => [
    ...props.attachments
        .filter((file) => fileKind(file.mime, file.name) !== 'image')
        .map((file) => {
            const kind = fileKind(file.mime, file.name);
            const tone =
                kind === 'text' ? TONES.text : (TONES[kind] ?? TONES.text);
            const extension = file.name.includes('.')
                ? file.name.split('.').pop()?.toUpperCase()
                : '';

            return {
                key: file.id,
                name: file.name,
                size: file.size,
                tone:
                    extension && kind === 'text'
                        ? { ...tone, label: extension.slice(0, 4) }
                        : tone,
                detail: formatSize(file.size),
                viewer: {
                    url: file.url,
                    name: file.name,
                    mime: file.mime,
                    thumb: file.thumb_url ?? null,
                },
                download: `${file.url}?descargar=1`,
                thumb: file.thumb_url ?? null,
            };
        }),
    ...props.artifacts.map((report) => ({
        key: report.id,
        name: `${report.title}.pdf`,
        size: report.size,
        tone: TONES.report,
        detail: formatSize(report.size),
        viewer: {
            url: report.preview_url ?? report.url,
            name: `${report.title}.pdf`,
            mime: 'application/pdf',
            downloadUrl: report.url,
            thumb: report.thumb_url ?? null,
        },
        download: report.url,
        thumb: report.thumb_url ?? null,
    })),
]);

const openImage = (file: ChatAttachment): void =>
    emit('open', { url: file.url, name: file.name, mime: file.mime });
</script>

<template>
    <div class="w-[min(20rem,74vw)] max-w-full space-y-2.5 p-1">
        <div
            v-if="images.length"
            class="grid gap-1.5 overflow-hidden rounded-2xl"
            :class="images.length === 1 ? 'grid-cols-1' : 'grid-cols-2'"
        >
            <button
                v-for="image in images"
                :key="image.id"
                type="button"
                class="group/img relative overflow-hidden bg-slate-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#23c6c8]"
                :class="images.length === 1 ? 'min-h-28' : 'aspect-square'"
                :aria-label="`Ver ${image.name}`"
                @click="openImage(image)"
            >
                <img
                    :src="image.url"
                    :alt="image.name"
                    loading="lazy"
                    class="block w-full object-cover transition duration-500 group-hover/img:scale-[1.03] motion-reduce:transition-none"
                    :class="images.length === 1 ? 'max-h-80' : 'size-full'"
                />
                <span
                    class="absolute inset-0 flex items-center justify-center bg-[#0a161e]/0 opacity-0 transition duration-300 group-hover/img:bg-[#0a161e]/25 group-hover/img:opacity-100 motion-reduce:transition-none"
                    aria-hidden="true"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-full bg-white/90 text-[#0a161e] shadow-lg"
                    >
                        <Expand class="size-4" />
                    </span>
                </span>
            </button>
        </div>

        <article
            v-for="card in cards"
            :key="card.key"
            class="group/file overflow-hidden rounded-2xl border transition duration-300 hover:-translate-y-0.5 hover:shadow-lg motion-reduce:transform-none motion-reduce:transition-none"
            :class="
                own
                    ? 'border-white/10 bg-white/[0.06] hover:bg-white/10'
                    : 'border-slate-200/80 bg-white shadow-sm hover:border-[#23c6c8]/50 hover:shadow-cyan-900/5'
            "
        >
            <button
                type="button"
                class="focus-visible:outline-inset relative flex w-full items-center justify-center overflow-hidden focus-visible:outline-2 focus-visible:outline-[#23c6c8]"
                :class="
                    hasThumb(card)
                        ? 'h-52 bg-slate-100'
                        : `h-24 bg-gradient-to-br ${card.tone.header}`
                "
                :aria-label="`Ver ${card.name}`"
                @click="emit('open', card.viewer)"
            >
                <template v-if="hasThumb(card)">
                    <!-- Primera página real del PDF: ocupa la tarjeta y se desvanece hacia el pie. -->
                    <img
                        :src="card.thumb ?? undefined"
                        :alt="`Primera página de ${card.name}`"
                        loading="lazy"
                        decoding="async"
                        class="absolute inset-x-6 top-5 bottom-0 h-auto w-[calc(100%-3rem)] rounded-t-lg bg-white object-cover object-top shadow-[0_-6px_28px_-12px_rgba(10,22,30,0.28)] ring-1 ring-slate-900/5 transition duration-500 group-hover/file:-translate-y-1 motion-reduce:transform-none"
                        @error="markBroken(card.key)"
                    />
                    <span
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-slate-100 to-transparent"
                        aria-hidden="true"
                    />
                </template>
                <template v-else>
                    <span
                        class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle,rgba(10,22,30,0.07)_1px,transparent_1px)] [background-size:14px_14px]"
                        aria-hidden="true"
                    />
                    <span
                        class="relative flex size-14 items-center justify-center rounded-2xl bg-white shadow-xl transition duration-300 group-hover/file:scale-105 motion-reduce:transform-none"
                    >
                        <FileText class="size-7" :class="card.tone.icon" />
                    </span>
                </template>
                <span
                    class="absolute top-2.5 right-2.5 rounded-md bg-white/90 px-2 py-0.5 text-[10px] font-bold tracking-wider text-slate-600 shadow-sm ring-1 ring-slate-200"
                >
                    {{ card.tone.label }}
                </span>
                <span
                    class="absolute bottom-2.5 left-3 flex items-center gap-1 text-[11px] font-medium text-white/90 opacity-0 transition duration-300 group-hover/file:opacity-100 motion-reduce:transition-none"
                >
                    <Expand class="size-3.5" aria-hidden="true" />
                    Vista previa
                </span>
            </button>

            <div class="flex items-center gap-3 px-3.5 py-3">
                <div class="min-w-0 flex-1">
                    <p
                        class="truncate text-sm font-semibold"
                        :class="own ? 'text-white' : 'text-slate-900'"
                        :title="card.name"
                    >
                        {{ card.name }}
                    </p>
                    <p
                        class="mt-0.5 text-xs tabular-nums"
                        :class="own ? 'text-slate-300' : 'text-slate-500'"
                    >
                        {{ card.tone.label }} · {{ card.detail }}
                    </p>
                </div>
                <a
                    :href="card.download"
                    :download="card.name"
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl transition duration-200 focus-visible:outline-2 focus-visible:outline-[#23c6c8] motion-reduce:transition-none"
                    :class="
                        own
                            ? 'bg-white/10 text-white hover:bg-white/20'
                            : 'bg-cyan-50 text-[#139b9e] hover:bg-[#0a161e] hover:text-[#23c6c8]'
                    "
                    :aria-label="`Descargar ${card.name}`"
                    title="Descargar"
                >
                    <Download class="size-4" aria-hidden="true" />
                </a>
            </div>
        </article>
    </div>
</template>
