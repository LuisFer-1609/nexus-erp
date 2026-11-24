<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import type { Mood } from '@/composables/useNexusMood';
import { useNexusMood } from '@/composables/useNexusMood';
import { MASCOT_LAYOUT as L } from '@/lib/nexusMascotLayout';
import type { MascotInstance } from '@/lib/nexusMascotEngine';
import { registerMascot, wake } from '@/lib/nexusMascotEngine';

/**
 * Mascota viva de Nexus: sigue el mouse con la cabeza y los ojos, parpadea, respira y cambia de
 * emoción según lo que pasa en la conversación (ver useNexusMood).
 *
 * Es 2.5D, no un modelo 3D: cuerpo y cabeza son dos imágenes WebP (~12 kB en total) y los ojos
 * son SVG. Todo el movimiento es `transform`/`opacity`.
 */
const props = withDefaults(
    defineProps<{
        /** head: solo la cabeza (iconos). full: cuerpo completo. */
        variant?: 'head' | 'full';
        /** Acerca la cabeza dentro de su contenedor (el contenedor recorta con overflow-hidden). */
        zoom?: number;
        /** Seguir el puntero. Si es false mira siempre al frente. */
        tracking?: boolean;
    }>(),
    { variant: 'head', zoom: 1, tracking: true },
);

const { mood, watchActivity } = useNexusMood();

const uid = useId();
const clipId = `${uid}-visor`;
const sweepId = `${uid}-sweep`;
const blushId = `${uid}-blush`;

// Geometría de los ojos, en las unidades de la imagen de la cabeza.
const u = L.unit;
const arcHalf = 0.139 * u;
const arcRise = 0.0725 * u;
const stroke = 0.1 * u;
const arcPath = `M ${-arcHalf} ${arcRise / 2} Q 0 ${-arcRise * 1.5} ${arcHalf} ${arcRise / 2}`;
const roundRx = 0.108 * u;
const roundRy = 0.132 * u;
const eyes = [
    { side: 'l', x: L.eyeLeft[0], y: L.eyeLeft[1] },
    { side: 'r', x: L.eyeRight[0], y: L.eyeRight[1] },
] as const;
const blushes = eyes.map((eye) => ({
    x: eye.x + (eye.side === 'l' ? -0.05 : 0.05) * u,
    y: eye.y + 0.2 * u,
}));
const [visorX, visorY] = L.visor;
const [visorRx, visorRy] = L.visorRadius;

const rect = (r: { left: number; top: number; w: number; h: number }) => ({
    left: `${r.left}%`,
    top: `${r.top}%`,
    width: `${r.w}%`,
    height: `${r.h}%`,
});

const rootStyle =
    props.variant === 'full'
        ? {
              width: '100%',
              aspectRatio: `${L.canvas.width} / ${L.canvas.height}`,
              '--sweep': `${visorRx * 2}px`,
          }
        : {
              width: `${92 * props.zoom}%`,
              aspectRatio: `${L.head.width} / ${L.head.height}`,
              '--sweep': `${visorRx * 2}px`,
          };

const rootEl = ref<HTMLElement | null>(null);
const headEl = ref<HTMLElement | null>(null);
const eyesEl = ref<SVGGElement | null>(null);
const blinking = ref(false);
const visible = ref(true);

/** La mirada que pide el ánimo (hacia el campo de texto, hacia arriba pensando...). */
const gaze = (): { x: number; y: number } | null => {
    if (!props.tracking) {
        return { x: 0, y: 0 };
    }

    switch (mood.value) {
        case 'listening':
            return { x: 0, y: 0.95 };
        case 'thinking':
            return { x: -0.35, y: -0.55 };
        case 'sleepy':
            return { x: 0, y: 0.6 };
        case 'worried':
            return { x: 0, y: 0.35 };
        default:
            return null;
    }
};

const BLINKABLE: Mood[] = [
    'neutral',
    'listening',
    'cute',
    'thinking',
    'surprised',
];
let blinkTimer: ReturnType<typeof setTimeout> | undefined;
const timers = new Set<ReturnType<typeof setTimeout>>();

const blinkOnce = (): void => {
    blinking.value = true;
    const t = setTimeout(() => {
        blinking.value = false;
        timers.delete(t);
    }, 110);
    timers.add(t);
};

const scheduleBlink = (): void => {
    blinkTimer = setTimeout(
        () => {
            if (visible.value && BLINKABLE.includes(mood.value)) {
                blinkOnce();

                if (Math.random() < 0.2) {
                    // Parpadeo doble, como una persona.
                    const t = setTimeout(blinkOnce, 240);
                    timers.add(t);
                }
            }

            scheduleBlink();
        },
        2600 + Math.random() * 3600,
    );
};

let unregister: (() => void) | undefined;
let stopWatching: (() => void) | undefined;
let intersection: IntersectionObserver | undefined;
let resize: ResizeObserver | undefined;

onMounted(() => {
    const root = rootEl.value;
    const head = headEl.value;

    if (!root || !head) {
        return;
    }

    const instance: MascotInstance = {
        root,
        head,
        eyes: eyesEl.value,
        gaze,
        visible: true,
        width: root.offsetWidth,
        cx: 0,
        cy: 0,
    };

    unregister = registerMascot(instance);
    stopWatching = watchActivity();

    intersection = new IntersectionObserver(([entry]) => {
        instance.visible = visible.value = entry.isIntersecting;

        if (entry.isIntersecting) {
            wake();
        }
    });
    intersection.observe(root);

    resize = new ResizeObserver(() => {
        instance.width = root.offsetWidth;
    });
    resize.observe(root);

    scheduleBlink();
});

// Cambiar de ánimo puede cambiar hacia dónde mira: despierta el motor.
watch(mood, () => wake());

onBeforeUnmount(() => {
    unregister?.();
    stopWatching?.();
    intersection?.disconnect();
    resize?.disconnect();
    clearTimeout(blinkTimer);
    timers.forEach((t) => clearTimeout(t));
});
</script>

<template>
    <span class="flex shrink-0 items-center justify-center" aria-hidden="true">
        <div
            ref="rootEl"
            class="mascot relative"
            :data-mood="mood"
            :data-blink="blinking"
            :style="rootStyle"
        >
            <template v-if="variant === 'full'">
                <div class="absolute" :style="rect(L.body)">
                    <div class="body-mood size-full">
                        <img
                            src="/images/nexus/nexus-body.webp"
                            alt=""
                            :width="L.body.width"
                            :height="L.body.height"
                            class="pointer-events-none block size-full select-none"
                            draggable="false"
                            decoding="async"
                        />
                    </div>
                </div>
                <!-- Sombra de contacto bajo la barbilla (no viene en la imagen del cuerpo). -->
                <span class="chin absolute" />
            </template>

            <div
                ref="headEl"
                class="head absolute"
                :class="variant === 'head' ? 'inset-0' : ''"
                :style="variant === 'full' ? rect(L.head) : undefined"
            >
                <div class="head-mood size-full">
                    <div class="relative size-full">
                        <img
                            src="/images/nexus/nexus-head.webp"
                            alt=""
                            :width="L.head.width"
                            :height="L.head.height"
                            class="pointer-events-none block size-full select-none"
                            draggable="false"
                            decoding="async"
                        />
                        <svg
                            class="pointer-events-none absolute inset-0 size-full"
                            :viewBox="`0 0 ${L.head.width} ${L.head.height}`"
                            focusable="false"
                        >
                            <defs>
                                <clipPath :id="clipId">
                                    <ellipse
                                        :cx="visorX"
                                        :cy="visorY"
                                        :rx="visorRx"
                                        :ry="visorRy"
                                    />
                                </clipPath>
                                <linearGradient
                                    :id="sweepId"
                                    x1="0"
                                    x2="1"
                                    y1="0"
                                    y2="0"
                                >
                                    <stop
                                        offset="0"
                                        stop-color="#7ee8ff"
                                        stop-opacity="0"
                                    />
                                    <stop
                                        offset="0.5"
                                        stop-color="#7ee8ff"
                                        stop-opacity="0.6"
                                    />
                                    <stop
                                        offset="1"
                                        stop-color="#7ee8ff"
                                        stop-opacity="0"
                                    />
                                </linearGradient>
                                <radialGradient :id="blushId">
                                    <stop
                                        offset="0"
                                        stop-color="#ff8fb4"
                                        stop-opacity="0.85"
                                    />
                                    <stop
                                        offset="1"
                                        stop-color="#ff8fb4"
                                        stop-opacity="0"
                                    />
                                </radialGradient>
                            </defs>

                            <!-- Barrido de "analizando" dentro del visor. -->
                            <g :clip-path="`url(#${clipId})`">
                                <rect
                                    class="sweep"
                                    :x="visorX - visorRx"
                                    :y="visorY - visorRy"
                                    :width="visorRx * 0.55"
                                    :height="visorRy * 2"
                                    :fill="`url(#${sweepId})`"
                                />
                            </g>

                            <!-- Rubor. -->
                            <g class="blush">
                                <ellipse
                                    v-for="(b, i) in blushes"
                                    :key="i"
                                    :cx="b.x"
                                    :cy="b.y"
                                    :rx="0.14 * u"
                                    :ry="0.075 * u"
                                    :fill="`url(#${blushId})`"
                                />
                            </g>

                            <g ref="eyesEl" class="eyes">
                                <g class="scanner">
                                    <g
                                        class="lids"
                                        :style="{
                                            transformOrigin: `0px ${eyes[0].y}px`,
                                        }"
                                    >
                                        <g
                                            v-for="eye in eyes"
                                            :key="eye.side"
                                            :transform="`translate(${eye.x} ${eye.y})`"
                                        >
                                            <g
                                                class="eye"
                                                :data-side="eye.side"
                                            >
                                                <g class="shape eye-arc">
                                                    <path
                                                        :d="arcPath"
                                                        fill="none"
                                                        stroke="#58a8ff"
                                                        stroke-opacity="0.5"
                                                        stroke-linecap="round"
                                                        :stroke-width="
                                                            stroke * 1.9
                                                        "
                                                    />
                                                    <path
                                                        :d="arcPath"
                                                        fill="none"
                                                        stroke="#e2fcff"
                                                        stroke-linecap="round"
                                                        :stroke-width="stroke"
                                                    />
                                                </g>
                                                <g class="shape eye-round">
                                                    <ellipse
                                                        :rx="roundRx * 1.28"
                                                        :ry="roundRy * 1.22"
                                                        fill="#58a8ff"
                                                        fill-opacity="0.45"
                                                    />
                                                    <ellipse
                                                        :rx="roundRx"
                                                        :ry="roundRy"
                                                        fill="#e8fdff"
                                                    />
                                                    <circle
                                                        :cx="-roundRx * 0.3"
                                                        :cy="-roundRy * 0.32"
                                                        :r="roundRx * 0.3"
                                                        fill="#7cc4ff"
                                                        fill-opacity="0.9"
                                                    />
                                                    <circle
                                                        :cx="roundRx * 0.34"
                                                        :cy="roundRy * 0.38"
                                                        :r="roundRx * 0.15"
                                                        fill="#7cc4ff"
                                                        fill-opacity="0.9"
                                                    />
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </span>
</template>

<style scoped>
.mascot {
    --ease: cubic-bezier(0.34, 1.45, 0.64, 1);
}

.head {
    transform-origin: 50% 88%;
    will-change: transform;
}

.head-mood {
    transform-origin: 50% 90%;
    animation: idle 4.4s ease-in-out infinite;
}

.body-mood {
    transform-origin: 50% 100%;
    animation: breathe 4.4s ease-in-out infinite;
}

.chin {
    left: 17%;
    top: 55%;
    width: 66%;
    height: 11%;
    background: radial-gradient(
        closest-side,
        rgba(10, 22, 30, 0.34),
        rgba(10, 22, 30, 0)
    );
}

/* Ojos: cada forma se cambia con transform/opacity. */
.shape,
.eye {
    transform-box: fill-box;
    transform-origin: 50% 50%;
}

.shape {
    transition:
        transform 0.26s var(--ease),
        opacity 0.18s ease;
}

.eye {
    transition: transform 0.26s var(--ease);
}

.lids {
    transform-box: view-box;
    transition: transform 0.07s ease-in;
}

.eye-round {
    opacity: 0;
    transform: scale(0.55);
}

.blush {
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sweep {
    opacity: 0;
}

.mascot[data-blink='true'] .lids {
    transform: scaleY(0.08);
}

/* Estados de ánimo */
.mascot[data-mood='listening'] .eye-arc,
.mascot[data-mood='thinking'] .eye-arc,
.mascot[data-mood='cute'] .eye-arc,
.mascot[data-mood='surprised'] .eye-arc {
    opacity: 0;
    transform: scale(0.7);
}

.mascot[data-mood='listening'] .eye-round {
    opacity: 1;
    transform: scale(0.8);
}

.mascot[data-mood='thinking'] .eye-round {
    opacity: 1;
    transform: scale(0.5);
}

.mascot[data-mood='cute'] .eye-round {
    opacity: 1;
    transform: scale(1);
}

.mascot[data-mood='surprised'] .eye-round {
    opacity: 1;
    transform: scale(1.14);
}

.mascot[data-mood='happy'] .eye-arc {
    transform: scale(1.1, 1.25);
}

.mascot[data-mood='worried'] .eye-arc {
    transform: scaleY(0.85);
}

.mascot[data-mood='worried'] .eye[data-side='l'] {
    transform: rotate(-14deg);
}

.mascot[data-mood='worried'] .eye[data-side='r'] {
    transform: rotate(14deg);
}

.mascot[data-mood='wink'] .eye[data-side='r'] .eye-arc {
    transform: scaleY(0.1);
}

.mascot[data-mood='sleepy'] .eye-arc {
    transform: scaleY(0.28);
    opacity: 0.85;
}

.mascot[data-mood='cute'] .blush {
    opacity: 1;
}

.mascot[data-mood='happy'] .blush {
    opacity: 0.55;
}

.mascot[data-mood='thinking'] .scanner {
    animation: scan 1.5s ease-in-out infinite alternate;
}

.mascot[data-mood='thinking'] .sweep {
    opacity: 1;
    animation: sweep 1.6s linear infinite;
}

/* Movimiento del cuerpo y la cabeza por estado */
.mascot[data-mood='listening'] .head-mood {
    animation: nod 1.8s ease-in-out infinite;
}

.mascot[data-mood='thinking'] .head-mood {
    animation: think 2.6s ease-in-out infinite;
}

.mascot[data-mood='happy'] .head-mood {
    animation: laugh 0.56s ease-in-out infinite;
}

.mascot[data-mood='happy'] .body-mood {
    animation: hop 0.56s ease-in-out infinite;
}

.mascot[data-mood='cute'] .head-mood {
    animation: cute 2.4s ease-in-out infinite;
}

.mascot[data-mood='surprised'] .head-mood {
    animation: pop 0.4s ease-out 1 both;
}

.mascot[data-mood='worried'] .head-mood {
    animation: worry 3s ease-in-out infinite;
}

.mascot[data-mood='sleepy'] .head-mood {
    animation: sleep 5.5s ease-in-out infinite;
}

.mascot[data-mood='sleepy'] .body-mood {
    animation-duration: 6.5s;
}

@keyframes idle {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-1.6%) rotate(0.4deg);
    }
}

@keyframes breathe {
    0%,
    100% {
        transform: scale(1, 1);
    }
    50% {
        transform: scale(1.012, 1.02);
    }
}

@keyframes nod {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(2.2%) rotate(0.8deg);
    }
}

@keyframes think {
    0%,
    100% {
        transform: rotate(-2.2deg);
    }
    50% {
        transform: rotate(2.2deg) translateY(-1%);
    }
}

@keyframes laugh {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    20% {
        transform: translateY(-5%) rotate(-3deg);
    }
    40% {
        transform: translateY(1%) rotate(2.5deg);
    }
    60% {
        transform: translateY(-4%) rotate(-2deg);
    }
    80% {
        transform: translateY(1%) rotate(1.5deg);
    }
}

@keyframes hop {
    0%,
    100% {
        transform: scale(1, 1);
    }
    30% {
        transform: scale(1.015, 0.985);
    }
    60% {
        transform: scale(0.99, 1.02);
    }
}

@keyframes cute {
    0%,
    100% {
        transform: translateY(0) rotate(3deg);
    }
    50% {
        transform: translateY(-1.4%) rotate(-3deg);
    }
}

@keyframes pop {
    0% {
        transform: scale(1);
    }
    35% {
        transform: scale(1.09) translateY(-2%);
    }
    100% {
        transform: scale(1);
    }
}

@keyframes worry {
    0%,
    100% {
        transform: translateY(2%) rotate(-2deg);
    }
    50% {
        transform: translateY(3%) rotate(-3.2deg);
    }
}

@keyframes sleep {
    0%,
    100% {
        transform: translateY(5%) rotate(2deg);
    }
    50% {
        transform: translateY(7%) rotate(3.4deg);
    }
}

@keyframes scan {
    from {
        transform: translateX(-9px);
    }
    to {
        transform: translateX(9px);
    }
}

@keyframes sweep {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(var(--sweep));
    }
}

@media (prefers-reduced-motion: reduce) {
    .head-mood,
    .body-mood,
    .scanner,
    .sweep {
        animation: none !important;
    }

    .shape,
    .eye,
    .lids,
    .blush {
        transition: none;
    }
}
</style>
