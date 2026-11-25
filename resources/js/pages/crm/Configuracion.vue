<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type Business = { id: string; name: string };

const props = defineProps<{
    hasConnection: boolean;
    businessName: string | null;
    metaConfigured: boolean;
}>();
const connected = ref(props.hasConnection);
const page = usePage();
const businessForm = useForm({ business_id: '' });
const businesses = ref<Business[]>([]);
const error = ref('');

async function loadBusinesses() {
    if (!connected.value) return;
    try {
        const response = await fetch('/crm/meta/businesses', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok)
            throw new Error(
                (await response.json()).message ||
                    'No se pudieron consultar los Business Managers.',
            );
        businesses.value = (await response.json()).data;
    } catch (cause) {
        error.value =
            cause instanceof Error ? cause.message : 'Meta no respondió.';
    }
}

function selectBusiness() {
    businessForm.post('/crm/meta/business');
}

onMounted(() => {
    if (page.props.errors?.meta) error.value = String(page.props.errors.meta);
    void loadBusinesses();
});
</script>

<template>
    <Head title="Configuración" />
    <NexusPageHeader
        eyebrow="CRM / Configuración"
        title="Configuración"
        description="Administra la empresa, usuarios, roles e integraciones del CRM."
    />

    <p
        v-if="error"
        role="alert"
        class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
        {{ error }}
    </p>

    <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="text-lg font-semibold">Business Manager de Meta</h2>
        <p class="mt-1 text-sm text-slate-500">
            Conecta Meta para elegir cuentas publicitarias. El Business Manager
            se usa para vincular páginas y sus leads.
        </p>
        <p
            v-if="businessName"
            class="mt-3 text-sm font-medium text-emerald-700"
        >
            Conectado a {{ businessName }}
        </p>
        <p v-if="!metaConfigured" class="mt-3 text-sm text-amber-700">
            Falta configurar la app de Meta en el servidor.
        </p>
        <a
            v-else
            href="/crm/meta/oauth/redirect"
            class="mt-4 inline-block rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white"
        >
            {{ connected ? 'Reconectar con Meta' : 'Iniciar sesión con Meta' }}
        </a>
        <form
            v-if="connected && businesses.length"
            class="mt-4 flex flex-wrap items-end gap-3"
            @submit.prevent="selectBusiness"
        >
            <label class="text-sm"
                >Business Manager
                <select
                    v-model="businessForm.business_id"
                    required
                    class="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
                >
                    <option value="">Selecciona un negocio</option>
                    <option
                        v-for="business in businesses"
                        :key="business.id"
                        :value="business.id"
                    >
                        {{ business.name }}
                    </option>
                </select>
            </label>
            <button
                type="submit"
                :disabled="businessForm.processing"
                class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                {{ businessName ? 'Cambiar negocio' : 'Conectar negocio' }}
            </button>
        </form>
        <p
            v-if="connected && !businessName && businesses.length === 0"
            class="mt-3 text-sm text-slate-500"
        >
            No se encontraron Business Managers disponibles para esta cuenta.
        </p>
        <p
            v-if="businessForm.errors.business_id"
            class="mt-2 text-sm text-red-600"
        >
            {{ businessForm.errors.business_id }}
        </p>
    </section>
</template>
