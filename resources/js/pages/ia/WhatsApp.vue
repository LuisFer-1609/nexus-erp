<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CheckCircle2, Smartphone, TriangleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import NexusBadge from '@/components/nexus/NexusBadge.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { desvincular, estado, iniciar } from '@/routes/ia/whatsapp';

type Status = 'disconnected' | 'connecting' | 'qr' | 'connected';

const props = defineProps<{
    configured: boolean;
    link: { status: Status; phone: string | null; connectedAt: string | null };
}>();

const status = ref<Status>(props.link.status);
const phone = ref<string | null>(props.link.phone);
const qr = ref<string | null>(null);
const busy = ref(false);
const error = ref('');
let poller: ReturnType<typeof setInterval> | null = null;

const xsrf = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

type State = { status: Status; qr: string | null; phone: string | null };

const call = async (
    url: string,
    method: 'GET' | 'POST' | 'DELETE',
): Promise<State | null> => {
    try {
        const response = await fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrf(),
            },
            credentials: 'same-origin',
        });
        const data = (await response.json().catch(() => ({}))) as
            | State
            | { message?: string };

        if (!response.ok) {
            error.value =
                (data as { message?: string }).message ??
                'No se pudo completar la acción. Inténtalo de nuevo.';

            return null;
        }

        error.value = '';

        return data as State;
    } catch {
        error.value = 'No hay conexión con el servidor.';

        return null;
    }
};

const apply = (state: State): void => {
    status.value = state.status;
    qr.value = state.qr;
    phone.value = state.phone;
};

const stopPolling = (): void => {
    if (poller !== null) {
        clearInterval(poller);
        poller = null;
    }
};

const startPolling = (): void => {
    if (poller !== null) {
        return;
    }

    poller = setInterval(async () => {
        const state = await call(estado.url(), 'GET');

        if (state === null) {
            return;
        }

        apply(state);

        if (state.status === 'connected' || state.status === 'disconnected') {
            stopPolling();
        }
    }, 2500);
};

const link = async (): Promise<void> => {
    busy.value = true;
    const state = await call(iniciar.url(), 'POST');
    busy.value = false;

    if (state !== null) {
        apply(state);
        startPolling();
    }
};

const unlink = async (): Promise<void> => {
    busy.value = true;
    const state = await call(desvincular.url(), 'DELETE');
    busy.value = false;
    stopPolling();

    if (state !== null) {
        apply(state);
    }
};

const waiting = computed(
    () => status.value === 'connecting' || status.value === 'qr',
);

const badge = computed(() => {
    switch (status.value) {
        case 'connected':
            return { label: 'Vinculado', tone: 'green' as const };
        case 'qr':
            return { label: 'Esperando escaneo', tone: 'amber' as const };
        case 'connecting':
            return { label: 'Conectando…', tone: 'blue' as const };
        default:
            return { label: 'Sin vincular', tone: 'slate' as const };
    }
});

const steps = [
    'Abre WhatsApp en tu teléfono.',
    'Ve a Ajustes → Dispositivos vinculados.',
    'Toca «Vincular un dispositivo» y escanea el código.',
];

onMounted(async () => {
    if (!props.configured) {
        return;
    }

    const state = await call(estado.url(), 'GET');

    if (state !== null) {
        apply(state);

        if (waiting.value) {
            startPolling();
        }
    }
});
onBeforeUnmount(stopPolling);
</script>

<template>
    <Head title="WhatsApp" />

    <NexusPageHeader
        eyebrow="Nexus IA / WhatsApp"
        title="Vincula tu WhatsApp"
        description="Conecta tu número escaneando un código QR. Así Nexus podrá comunicarse contigo por WhatsApp."
    />

    <div
        v-if="!configured"
        role="alert"
        class="mb-6 flex items-start gap-3 rounded-[10px] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        <p>
            El servicio de WhatsApp aún no está configurado. Agrega
            <code class="font-semibold">WHATSAPP_BRIDGE_TOKEN</code> en el
            <code class="font-semibold">.env</code> e inícialo con
            <code class="font-semibold">npm run whatsapp</code>.
        </p>
    </div>

    <p
        v-if="error"
        role="alert"
        class="mb-4 flex items-start gap-3 rounded-[10px] border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
    >
        <TriangleAlert class="mt-0.5 size-4 shrink-0" />
        {{ error }}
    </p>

    <section
        class="grid gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:grid-cols-[minmax(0,1fr)_320px] md:p-7"
        aria-label="Vínculo de WhatsApp"
    >
        <div>
            <div class="flex items-center gap-3">
                <span
                    class="flex size-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"
                >
                    <Smartphone class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        Tu WhatsApp
                    </h2>
                    <NexusBadge :label="badge.label" :tone="badge.tone" />
                </div>
            </div>

            <template v-if="status === 'connected'">
                <p
                    class="mt-5 flex items-center gap-2 text-sm font-medium text-emerald-700"
                >
                    <CheckCircle2 class="size-5" />
                    Número vinculado
                    <span v-if="phone" class="tabular-nums">+{{ phone }}</span>
                </p>
                <p class="mt-2 text-sm leading-6 text-slate-500">
                    Tu cuenta quedó lista. Puedes desvincularla cuando quieras
                    desde aquí o desde tu teléfono.
                </p>
                <Button
                    class="mt-5"
                    variant="outline"
                    :disabled="busy"
                    @click="unlink"
                >
                    Desvincular WhatsApp
                </Button>
            </template>

            <template v-else>
                <ol class="mt-5 space-y-3 text-sm text-slate-600">
                    <li
                        v-for="(step, index) in steps"
                        :key="step"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-full bg-cyan-50 text-xs font-semibold text-[#139b9e]"
                        >
                            {{ index + 1 }}
                        </span>
                        {{ step }}
                    </li>
                </ol>
                <div class="mt-6 flex flex-wrap gap-2">
                    <Button
                        class="bg-[#23c6c8] text-[#0a161e] hover:bg-[#1fb5b7]"
                        :disabled="busy || waiting || !configured"
                        @click="link"
                    >
                        {{
                            waiting ? 'Generando código…' : 'Generar código QR'
                        }}
                    </Button>
                    <Button
                        v-if="waiting"
                        variant="outline"
                        :disabled="busy"
                        @click="unlink"
                    >
                        Cancelar
                    </Button>
                </div>
            </template>
        </div>

        <div
            class="flex min-h-[320px] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 p-4"
        >
            <img
                v-if="qr"
                :src="qr"
                alt="Código QR para vincular WhatsApp"
                class="size-[280px] rounded-lg bg-white p-2 shadow-sm"
            />
            <Skeleton
                v-else-if="waiting"
                class="size-[280px] rounded-lg"
                aria-label="Generando código QR"
            />
            <p
                v-else-if="status === 'connected'"
                class="max-w-[220px] text-center text-sm text-slate-500"
            >
                Tu WhatsApp ya está vinculado.
            </p>
            <p v-else class="max-w-[220px] text-center text-sm text-slate-500">
                El código QR aparecerá aquí al pulsar «Generar código QR».
            </p>
        </div>
    </section>
</template>
