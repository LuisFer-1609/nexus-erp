import { readonly, ref } from 'vue';

/**
 * Estado de ánimo compartido de la mascota de Nexus. Lo comparten todas las instancias
 * (botón flotante, encabezado, estado vacío, indicador de "escribiendo").
 */
export type Mood =
    | 'neutral'
    | 'listening' // el usuario está escribiendo: mira hacia el campo de texto
    | 'thinking' // analizando: espera la respuesta
    | 'happy' // risa
    | 'cute' // ojos tiernos y rubor
    | 'surprised'
    | 'worried'
    | 'wink'
    | 'sleepy';

type Reaction = { mood: Mood; ms: number };

const SLEEP_AFTER_MS = 90_000;

const mood = ref<Mood>('neutral');
let waiting = false;
let revertTimer: ReturnType<typeof setTimeout> | undefined;
let sleepTimer: ReturnType<typeof setTimeout> | undefined;
let watching = 0;

const armSleep = (): void => {
    clearTimeout(sleepTimer);
    sleepTimer = setTimeout(() => {
        if (mood.value === 'neutral' && !waiting) {
            mood.value = 'sleepy';
        }
    }, SLEEP_AFTER_MS);
};

/** Cambia de ánimo; con `ms` vuelve solo a "analizando" (si espera respuesta) o a normal. */
const set = (next: Mood, ms = 0): void => {
    clearTimeout(revertTimer);
    mood.value = next;
    armSleep();

    if (ms > 0) {
        revertTimer = setTimeout(() => {
            mood.value = waiting ? 'thinking' : 'neutral';
        }, ms);
    }
};

const plain = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase();

/** Reacción al texto del usuario según el tema; null si no hay nada que destacar. */
const reactionToUser = (text: string): Reaction | null => {
    const t = plain(text);

    if (/\b(ja){2,}|\bje(je)+|\bji(ji)+|\blol\b|\bxd+\b|😂|🤣/u.test(t)) {
        return { mood: 'happy', ms: 2200 };
    }

    if (
        /gracias|te agradezco|excelente|genial|perfecto|buen trabajo|eres (el|la) mejor|me encanta|te quiero|❤|💙|🥰/u.test(
            t,
        )
    ) {
        return { mood: 'cute', ms: 2200 };
    }

    if (
        /^(hola|holi|buenas|buenos dias|buenas tardes|buenas noches|hey|que tal)\b/.test(
            t,
        )
    ) {
        return { mood: 'wink', ms: 1500 };
    }

    if (
        /error|problema|falla|fallo|no funciona|urgente|se cayo|preocup|critico|ayuda/.test(
            t,
        )
    ) {
        return { mood: 'worried', ms: 2200 };
    }

    if (/\bwow\b|increible|no puede ser|en serio|!!|\?\?/.test(t)) {
        return { mood: 'surprised', ms: 1400 };
    }

    return null;
};

type Reply = { text?: string; hasReport?: boolean; failed?: boolean };

/** Reacción a la respuesta de Nexus: celebra reportes, se preocupa por errores. */
const reactionToReply = ({ text = '', hasReport, failed }: Reply): Reaction => {
    if (failed) {
        return { mood: 'worried', ms: 3600 };
    }

    if (hasReport) {
        return { mood: 'happy', ms: 3000 };
    }

    const t = plain(text);

    if (
        /no pude|no encontre|no tengo (acceso|informacion|datos)|lo siento|disculpa/.test(
            t,
        )
    ) {
        return { mood: 'worried', ms: 2600 };
    }

    if (/con gusto|de nada|listo|hecho|encantad|felicidades/.test(t)) {
        return { mood: 'cute', ms: 2200 };
    }

    // Variedad: no siempre la misma cara después de responder.
    const roll = Math.random();

    if (roll < 0.3) {
        return { mood: 'happy', ms: 1600 };
    }

    if (roll < 0.45) {
        return { mood: 'cute', ms: 1800 };
    }

    if (roll < 0.55) {
        return { mood: 'wink', ms: 1300 };
    }

    return { mood: 'neutral', ms: 0 };
};

const onActivity = (): void => {
    if (mood.value === 'sleepy') {
        set('surprised', 900);

        return;
    }

    armSleep();
};

export function useNexusMood() {
    /** Una mascota visible pide vigilar la actividad del usuario (para despertar). */
    const watchActivity = (): (() => void) => {
        if (watching++ === 0) {
            window.addEventListener('pointermove', onActivity, {
                passive: true,
            });
            armSleep();
        }

        return () => {
            if (--watching === 0) {
                window.removeEventListener('pointermove', onActivity);
                clearTimeout(sleepTimer);
            }
        };
    };

    return {
        mood: readonly(mood),
        set,
        watchActivity,
        /** El usuario escribe: la mascota escucha y mira hacia el texto. */
        typing: (): void => {
            if (!waiting) {
                set('listening', 1800);
            }
        },
        /** Se envió un mensaje: reacciona al tema y luego se pone a analizar. */
        onSend: (text: string): void => {
            waiting = true;
            const reaction = reactionToUser(text);

            if (reaction) {
                set(reaction.mood, reaction.ms);
            } else {
                set('thinking');
            }
        },
        /** Llegó la respuesta (o falló): reacciona a su contenido. */
        onReply: (reply: Reply): void => {
            waiting = false;
            const reaction = reactionToReply(reply);
            set(reaction.mood, reaction.ms);
        },
        /** Un toque cariñoso: una reacción al azar. */
        poke: (): void => {
            const options: Mood[] = ['happy', 'cute', 'surprised', 'wink'];
            set(options[Math.floor(Math.random() * options.length)], 1600);
        },
    };
}
