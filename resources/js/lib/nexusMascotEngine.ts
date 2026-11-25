/**
 * Motor de movimiento de la mascota. Un único bucle de animación para todas las instancias:
 * mueve la cabeza y los ojos hacia el puntero y se detiene por completo cuando todo está quieto
 * (sin trabajo mientras nadie mueve el mouse). Solo escribe `transform`, así que el navegador
 * lo resuelve en la GPU sin recalcular el diseño de la página.
 */
export type Gaze = { x: number; y: number } | null;

export interface MascotInstance {
    root: HTMLElement;
    head: HTMLElement;
    eyes: SVGGElement | null;
    /** Mirada forzada por el estado de ánimo (p. ej. hacia el campo de texto); null = seguir el puntero. */
    gaze: () => Gaze;
    visible: boolean;
    width: number;
    cx: number;
    cy: number;
}

const IDLE_RETURN_MS = 5000;

const instances = new Set<MascotInstance>();
const pointer = { x: 0, y: 0, last: 0, known: false };
let frame = 0;
let lastTime = 0;
let listening = false;
let idleTimer: ReturnType<typeof setTimeout> | undefined;

const reduced =
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const clamp = (value: number, min: number, max: number): number =>
    Math.min(max, Math.max(min, value));

const apply = (inst: MascotInstance): void => {
    const { cx, cy, width } = inst;

    inst.head.style.transform = `perspective(520px) rotateY(${(cx * 9).toFixed(2)}deg) rotateX(${(-cy * 7).toFixed(2)}deg) translate3d(${(cx * width * 0.014).toFixed(2)}px, ${(cy * width * 0.01).toFixed(2)}px, 0)`;

    if (inst.eyes) {
        // Los ojos se adelantan a la cabeza: da la sensación de que miran.
        inst.eyes.style.transform = `translate(${(cx * 9).toFixed(2)}px, ${(cy * 5.5).toFixed(2)}px)`;
    }
};

const tick = (now: number): void => {
    frame = 0;
    const dt = Math.min(0.05, (now - lastTime) / 1000);
    lastTime = now;
    const ease = 1 - Math.exp(-dt * 8);
    const following = pointer.known && now - pointer.last < IDLE_RETURN_MS;
    let busy = false;

    for (const inst of instances) {
        if (!inst.visible) {
            continue;
        }

        let tx = 0;
        let ty = 0;
        const forced = inst.gaze();

        if (forced) {
            tx = forced.x;
            ty = forced.y;
        } else if (following) {
            const rect = inst.root.getBoundingClientRect();
            tx = clamp((pointer.x - (rect.left + rect.width / 2)) / 380, -1, 1);
            ty = clamp((pointer.y - (rect.top + rect.height / 2)) / 300, -1, 1);
        }

        inst.cx += (tx - inst.cx) * ease;
        inst.cy += (ty - inst.cy) * ease;
        apply(inst);

        if (Math.abs(tx - inst.cx) > 0.003 || Math.abs(ty - inst.cy) > 0.003) {
            busy = true;
        }
    }

    if (busy) {
        frame = requestAnimationFrame(tick);
    }
};

/** Despierta el bucle (si estaba dormido). */
export const wake = (): void => {
    if (!frame && instances.size && !reduced) {
        lastTime = performance.now();
        frame = requestAnimationFrame(tick);
    }
};

const onMove = (event: PointerEvent): void => {
    pointer.x = event.clientX;
    pointer.y = event.clientY;
    pointer.last = performance.now();
    pointer.known = true;
    wake();

    // Pasados unos segundos sin mover el mouse, la mascota vuelve a mirar al frente.
    clearTimeout(idleTimer);
    idleTimer = setTimeout(wake, IDLE_RETURN_MS + 200);
};

export const registerMascot = (inst: MascotInstance): (() => void) => {
    instances.add(inst);

    if (!listening) {
        window.addEventListener('pointermove', onMove, { passive: true });
        window.addEventListener('scroll', wake, {
            passive: true,
            capture: true,
        });
        listening = true;
    }

    wake();

    return () => {
        instances.delete(inst);

        if (instances.size === 0 && listening) {
            window.removeEventListener('pointermove', onMove);
            window.removeEventListener('scroll', wake, true);
            cancelAnimationFrame(frame);
            clearTimeout(idleTimer);
            frame = 0;
            listening = false;
        }
    };
};
