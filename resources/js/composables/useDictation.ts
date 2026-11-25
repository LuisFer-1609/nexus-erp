import { onBeforeUnmount, ref } from 'vue';

/**
 * Dictado por voz con el reconocimiento del navegador (Web Speech API).
 * No hay tipos estándar en lib.dom, así que se declara solo lo que se usa.
 */
type SpeechResultItem = { isFinal: boolean; 0: { transcript: string } };
type SpeechResultEvent = {
    resultIndex: number;
    results: ArrayLike<SpeechResultItem>;
};
type SpeechErrorEvent = { error: string };

interface Recognition {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    maxAlternatives: number;
    onresult: ((event: SpeechResultEvent) => void) | null;
    onerror: ((event: SpeechErrorEvent) => void) | null;
    onend: (() => void) | null;
    start: () => void;
    stop: () => void;
    abort: () => void;
}

type RecognitionConstructor = new () => Recognition;

const constructorOf = (): RecognitionConstructor | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    const scope = window as unknown as {
        SpeechRecognition?: RecognitionConstructor;
        webkitSpeechRecognition?: RecognitionConstructor;
    };

    return scope.SpeechRecognition ?? scope.webkitSpeechRecognition ?? null;
};

/** Sin voz durante este tiempo, el dictado se apaga solo para no dejar el micrófono abierto. */
const IDLE_STOP_MS = 45_000;
/** Si la escucha se cierra antes de este tiempo y sin voz, cuenta como un fallo (micrófono o red). */
const RAPID_END_MS = 600;
const MAX_RAPID_FAILURES = 5;
const RETRY_DELAY_MS = 100;

/** Errores que impiden seguir escuchando, con un mensaje claro para el usuario. */
const FATAL_ERRORS: Record<string, string> = {
    'not-allowed':
        'Permite el acceso al micrófono en tu navegador para poder dictar.',
    'service-not-allowed':
        'Tu navegador no permite el dictado por voz en este momento.',
    'audio-capture': 'No encontré un micrófono disponible.',
    network:
        'No se pudo conectar con el servicio de voz. Revisa tu conexión e inténtalo de nuevo.',
    'language-not-supported': 'El dictado no está disponible en tu idioma.',
};

export function useDictation(options: {
    /** Texto dictado en esta sesión (lo ya confirmado más lo que se va entendiendo). */
    onUpdate: (spoken: string) => void;
    onError: (message: string) => void;
}) {
    const Ctor = constructorOf();
    const supported = Ctor !== null;
    const listening = ref(false);

    let recognition: Recognition | null = null;
    let wanted = false;
    let discard = false;
    let confirmed = '';
    let restartTimer: ReturnType<typeof setTimeout> | undefined;
    let idleTimer: ReturnType<typeof setTimeout> | undefined;

    const language = (): string =>
        navigator.language?.toLowerCase().startsWith('es')
            ? navigator.language
            : 'es-MX';

    const clearTimers = (): void => {
        clearTimeout(restartTimer);
        clearTimeout(idleTimer);
    };

    const armIdle = (): void => {
        clearTimeout(idleTimer);
        idleTimer = setTimeout(() => stop(), IDLE_STOP_MS);
    };

    let startedAt = 0;
    let heard = false;
    let rapidFailures = 0;
    let retrying = false;

    const finish = (): void => {
        recognition = null;
        listening.value = false;
        clearTimers();
    };

    const fail = (message: string): void => {
        wanted = false;
        options.onError(message);

        if (recognition) {
            recognition.abort();
        }

        finish();
    };

    /** Arranca (o reanuda) la escucha con la MISMA instancia, para no perder lo que se dice entre sesiones. */
    const run = (initial = false): void => {
        if (!recognition || !wanted) {
            return;
        }

        retrying = false;
        startedAt = Date.now();
        heard = false;

        try {
            recognition.start();
        } catch {
            if (initial) {
                fail('No se pudo iniciar el dictado. Inténtalo de nuevo.');

                return;
            }

            // El navegador a veces aún no libera la sesión anterior: reintento breve.
            rapidFailures++;

            if (rapidFailures >= MAX_RAPID_FAILURES) {
                fail('No se pudo seguir escuchando. Inténtalo de nuevo.');

                return;
            }

            retrying = true;
            restartTimer = setTimeout(run, RETRY_DELAY_MS);
        }
    };

    const create = (Recognizer: RecognitionConstructor): Recognition => {
        const instance = new Recognizer();
        instance.lang = language();
        instance.continuous = true;
        instance.interimResults = true;
        instance.maxAlternatives = 1;

        instance.onresult = (event) => {
            if (discard) {
                return;
            }

            heard = true;
            rapidFailures = 0;
            let interim = '';

            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                const chunk = result[0].transcript.trim();

                if (result.isFinal) {
                    confirmed = `${confirmed} ${chunk}`.trim();
                } else {
                    interim = `${interim} ${chunk}`.trim();
                }
            }

            armIdle();
            options.onUpdate(`${confirmed} ${interim}`.trim());
        };

        instance.onerror = (event) => {
            const message = FATAL_ERRORS[event.error];

            // «no-speech» y «aborted» no son fallos: el navegador corta y se reanuda en onend.
            if (message) {
                wanted = false;
                options.onError(message);
            }
        };

        instance.onend = () => {
            if (!wanted) {
                finish();

                return;
            }

            // Cierres inmediatos y sin voz repetidos: algo falla de fondo, no se reintenta sin fin.
            if (!heard && Date.now() - startedAt < RAPID_END_MS) {
                rapidFailures++;
            } else {
                rapidFailures = 0;
            }

            if (rapidFailures >= MAX_RAPID_FAILURES) {
                fail(
                    'No logré escuchar tu voz. Revisa tu micrófono e inténtalo de nuevo.',
                );

                return;
            }

            // El navegador corta la escucha en cada pausa: se reanuda al instante sin perder lo dicho.
            run();
        };

        return instance;
    };

    const start = (): void => {
        if (!Ctor || listening.value) {
            return;
        }

        wanted = true;
        discard = false;
        confirmed = '';
        listening.value = true;
        rapidFailures = 0;
        armIdle();
        recognition = create(Ctor);
        run(true);
    };

    /** Termina el dictado y conserva lo que ya se entendió (puede llegar un último fragmento). */
    const stop = (): void => {
        wanted = false;
        clearTimers();

        // Con un reintento pendiente la instancia está inactiva y no avisará de su cierre.
        if (!recognition || retrying) {
            finish();

            return;
        }

        recognition.stop();
    };

    /** Termina el dictado y descarta cualquier fragmento pendiente (por ejemplo, al enviar). */
    const cancel = (): void => {
        discard = true;
        wanted = false;
        clearTimers();
        recognition?.abort();
        recognition = null;
        listening.value = false;
    };

    const toggle = (): void => (listening.value ? stop() : start());

    onBeforeUnmount(cancel);

    return { supported, listening, start, stop, cancel, toggle };
}
