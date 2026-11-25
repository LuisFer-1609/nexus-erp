import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

type Handlers = {
    onresult?: (event: unknown) => void;
    onerror?: (event: { error: string }) => void;
    onend?: () => void;
};

class FakeRecognition {
    static instances: FakeRecognition[] = [];
    lang = '';
    continuous = false;
    interimResults = false;
    maxAlternatives = 1;
    onresult: Handlers['onresult'] = undefined;
    onerror: Handlers['onerror'] = undefined;
    onend: Handlers['onend'] = undefined;
    started = 0;
    aborted = false;

    constructor() {
        FakeRecognition.instances.push(this);
    }

    start() {
        this.started++;
    }

    stop() {
        this.onend?.();
    }

    abort() {
        this.aborted = true;
        this.onend?.();
    }

    say(items: { text: string; final: boolean }[], resultIndex = 0) {
        this.onresult?.({
            resultIndex,
            results: items.map((i) => ({
                isFinal: i.final,
                0: { transcript: i.text },
            })),
        });
    }
}

const load = async () => {
    vi.resetModules();

    return (await import('./useDictation')).useDictation;
};

describe('useDictation', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        FakeRecognition.instances = [];
        vi.stubGlobal('window', { webkitSpeechRecognition: FakeRecognition });
        vi.stubGlobal('navigator', { language: 'es-MX' });
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    it('no está disponible si el navegador no soporta voz', async () => {
        vi.stubGlobal('window', {});
        const useDictation = await load();
        const dictation = useDictation({ onUpdate: vi.fn(), onError: vi.fn() });

        expect(dictation.supported).toBe(false);
    });

    it('acumula lo confirmado y lo provisional', async () => {
        const useDictation = await load();
        const onUpdate = vi.fn();
        const dictation = useDictation({ onUpdate, onError: vi.fn() });

        dictation.start();
        const rec = FakeRecognition.instances[0];
        expect(rec.lang).toBe('es-MX');
        expect(rec.continuous).toBe(true);
        expect(rec.interimResults).toBe(true);

        rec.say([{ text: 'hola', final: false }]);
        expect(onUpdate).toHaveBeenLastCalledWith('hola');

        rec.say([{ text: 'hola nexus', final: true }]);
        expect(onUpdate).toHaveBeenLastCalledWith('hola nexus');

        rec.say(
            [
                { text: 'hola nexus', final: true },
                { text: 'dime las', final: false },
            ],
            1,
        );
        expect(onUpdate).toHaveBeenLastCalledWith('hola nexus dime las');
    });

    it('se reanuda al instante con la misma instancia cuando el navegador corta, y conserva lo dicho', async () => {
        const useDictation = await load();
        const onUpdate = vi.fn();
        const dictation = useDictation({ onUpdate, onError: vi.fn() });

        dictation.start();
        const rec = FakeRecognition.instances[0];
        rec.say([{ text: 'primera parte', final: true }]);
        rec.onend?.();

        // Sin esperas ni instancia nueva: no se pierde nada de lo que se diga en la pausa.
        expect(rec.started).toBe(2);
        expect(FakeRecognition.instances).toHaveLength(1);
        expect(dictation.listening.value).toBe(true);

        rec.say([{ text: 'segunda parte', final: true }]);
        expect(onUpdate).toHaveBeenLastCalledWith(
            'primera parte segunda parte',
        );
    });

    it('si la escucha se cierra una y otra vez al instante y sin voz, se detiene con un aviso', async () => {
        const useDictation = await load();
        const onError = vi.fn();
        const dictation = useDictation({ onUpdate: vi.fn(), onError });

        dictation.start();
        const rec = FakeRecognition.instances[0];

        for (let i = 0; i < 6 && dictation.listening.value; i++) {
            rec.onend?.();
        }

        expect(dictation.listening.value).toBe(false);
        expect(onError).toHaveBeenCalledWith(
            expect.stringContaining('micrófono'),
        );
        expect(rec.started).toBeLessThanOrEqual(6);
    });

    it('detener durante un reintento pendiente no deja el dictado activo', async () => {
        const useDictation = await load();
        const dictation = useDictation({ onUpdate: vi.fn(), onError: vi.fn() });

        dictation.start();
        const rec = FakeRecognition.instances[0];
        rec.start = () => {
            throw new Error('InvalidStateError');
        };
        rec.onend?.();
        dictation.stop();
        vi.advanceTimersByTime(1000);

        expect(dictation.listening.value).toBe(false);
    });

    it('detener conserva lo dicho y deja de escuchar', async () => {
        const useDictation = await load();
        const dictation = useDictation({ onUpdate: vi.fn(), onError: vi.fn() });

        dictation.start();
        dictation.stop();
        vi.advanceTimersByTime(1000);

        expect(dictation.listening.value).toBe(false);
        expect(FakeRecognition.instances).toHaveLength(1);
    });

    it('cancelar descarta fragmentos que lleguen después', async () => {
        const useDictation = await load();
        const onUpdate = vi.fn();
        const dictation = useDictation({ onUpdate, onError: vi.fn() });

        dictation.start();
        const rec = FakeRecognition.instances[0];
        dictation.cancel();
        rec.say([{ text: 'tarde', final: true }]);

        expect(rec.aborted).toBe(true);
        expect(onUpdate).not.toHaveBeenCalled();
        expect(dictation.listening.value).toBe(false);
    });

    it('un permiso denegado detiene el dictado con un mensaje claro', async () => {
        const useDictation = await load();
        const onError = vi.fn();
        const dictation = useDictation({ onUpdate: vi.fn(), onError });

        dictation.start();
        const rec = FakeRecognition.instances[0];
        rec.onerror?.({ error: 'not-allowed' });
        rec.onend?.();
        vi.advanceTimersByTime(1000);

        expect(onError).toHaveBeenCalledWith(
            expect.stringContaining('micrófono'),
        );
        expect(dictation.listening.value).toBe(false);
        expect(FakeRecognition.instances).toHaveLength(1);
    });

    it('sin voz durante 45 s se apaga solo', async () => {
        const useDictation = await load();
        const dictation = useDictation({ onUpdate: vi.fn(), onError: vi.fn() });

        dictation.start();
        vi.advanceTimersByTime(45_100);

        expect(dictation.listening.value).toBe(false);
    });
});
