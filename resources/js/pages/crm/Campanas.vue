<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type Account = {
    id: string;
    name: string;
    currency: string;
    status: number;
    linked: boolean;
};
type Page = { id: string; name: string; linked: boolean };
type Campaign = {
    id: string;
    name: string;
    status: string | null;
    effective_status: string | null;
    objective: string | null;
    metrics: Record<string, string | number | object>;
    saved: boolean;
};

const props = defineProps<{
    companyName: string;
    canManage: boolean;
    hasConnection: boolean;
}>();
const connected = ref(props.hasConnection);
const connectionForm = useForm({ access_token: '' });
const accounts = ref<Account[]>([]);
const pages = ref<Page[]>([]);
const campaigns = ref<Campaign[]>([]);
const selectedAccountId = ref('');
const since = ref(
    new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10),
);
const until = ref(new Date().toISOString().slice(0, 10));
const search = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const notice = ref('');

const selectedAccount = computed(() =>
    accounts.value.find((account) => account.id === selectedAccountId.value),
);
const visibleCampaigns = computed(() =>
    campaigns.value.filter((campaign) =>
        campaign.name
            .toLocaleLowerCase()
            .includes(search.value.toLocaleLowerCase()),
    ),
);

async function loadAssets() {
    if (!connected.value) return;
    error.value = '';

    try {
        const response = await fetch('/crm/meta/ad-accounts', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok)
            throw new Error('No se pudieron consultar las cuentas de Meta.');
        accounts.value = (await response.json()).data;
        if (props.canManage) {
            const pageResponse = await fetch('/crm/meta/pages', {
                headers: { Accept: 'application/json' },
            });
            if (pageResponse.ok) pages.value = (await pageResponse.json()).data;
        }
    } catch (cause) {
        error.value =
            cause instanceof Error ? cause.message : 'Meta no respondió.';
    }
}

async function loadCampaigns() {
    campaigns.value = [];
    if (!selectedAccount.value?.linked) return;

    loading.value = true;
    error.value = '';
    try {
        const params = new URLSearchParams({
            account_id: selectedAccountId.value,
            since: since.value,
            until: until.value,
        });
        const response = await fetch(`/crm/meta/campaigns?${params}`, {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok)
            throw new Error('No se pudieron consultar las campañas de Meta.');
        campaigns.value = (await response.json()).data;
    } catch (cause) {
        error.value =
            cause instanceof Error ? cause.message : 'Meta no respondió.';
    } finally {
        loading.value = false;
    }
}

function connect() {
    connectionForm.post('/crm/meta/connection', {
        onSuccess: () => {
            connected.value = true;
            connectionForm.reset();
            notice.value = 'Conexión de Meta guardada.';
            void loadAssets();
        },
    });
}

function linkAccount(account: Account) {
    router.post(
        '/crm/meta/ad-accounts',
        { account_id: account.id },
        {
            onSuccess: () => {
                notice.value = 'Cuenta vinculada.';
                void loadAssets();
            },
            onError: (errors) => {
                error.value =
                    errors.meta ||
                    errors.account_id ||
                    'No se pudo vincular la cuenta.';
            },
        },
    );
}

function linkPage(page: Page) {
    router.post(
        '/crm/meta/pages',
        { page_id: page.id },
        {
            onSuccess: () => {
                notice.value =
                    'Página vinculada. Sus leads entrarán al CRM durante la próxima importación.';
                void loadAssets();
            },
            onError: (errors) => {
                error.value =
                    errors.meta ||
                    errors.page_id ||
                    'No se pudo vincular la página.';
            },
        },
    );
}

function saveVisible() {
    if (visibleCampaigns.value.length === 0) return;
    saving.value = true;
    router.post(
        '/crm/meta/campaigns/save',
        {
            account_id: selectedAccountId.value,
            since: since.value,
            until: until.value,
            campaign_ids: visibleCampaigns.value.map((campaign) => campaign.id),
        },
        {
            onSuccess: () => {
                notice.value =
                    'Campañas guardadas. Los datos que cambiaron se actualizaron.';
                void loadCampaigns();
            },
            onError: (errors) => {
                error.value =
                    errors.meta ||
                    errors.campaign_ids ||
                    'No se pudieron guardar las campañas.';
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function importLeads() {
    router.post(
        '/crm/meta/leads/import',
        {},
        {
            onSuccess: () => {
                notice.value = 'Importación de leads terminada.';
            },
            onError: (errors) => {
                error.value =
                    errors.meta || 'No se pudieron importar los leads.';
            },
        },
    );
}

onMounted(() => {
    void loadAssets();
});
</script>

<template>
    <Head title="Campañas de Meta" />
    <NexusPageHeader
        eyebrow="CRM / Meta"
        title="Campañas"
        :description="`Consulta campañas de ${companyName} y guarda las visibles cuando lo necesites.`"
    />

    <p
        v-if="notice"
        role="status"
        class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800"
    >
        {{ notice }}
    </p>
    <p
        v-if="error"
        role="alert"
        class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
        {{ error }}
    </p>

    <section
        v-if="canManage && !connected"
        class="mb-6 rounded-xl border border-slate-200 bg-white p-6"
    >
        <h2 class="text-lg font-semibold">Conectar Meta</h2>
        <p class="mt-1 text-sm text-slate-500">
            Pega un token con acceso a las cuentas de esta empresa. Se almacena
            cifrado y no vuelve a mostrarse.
        </p>
        <form
            class="mt-4 flex flex-col gap-3 sm:flex-row"
            @submit.prevent="connect"
        >
            <input
                v-model="connectionForm.access_token"
                type="password"
                required
                autocomplete="off"
                aria-label="Token de Meta"
                class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm"
            />
            <button
                type="submit"
                :disabled="connectionForm.processing"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
            >
                Conectar
            </button>
        </form>
        <p
            v-if="connectionForm.errors.access_token"
            class="mt-2 text-sm text-red-600"
        >
            {{ connectionForm.errors.access_token }}
        </p>
    </section>
    <NexusEmptyState
        v-if="!connected"
        description="El administrador de la empresa debe conectar Meta para consultar campañas."
    />

    <template v-else>
        <section class="mb-6 rounded-xl border border-slate-200 bg-white p-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Cuentas publicitarias</h2>
                <button
                    type="button"
                    class="text-sm font-medium text-cyan-700"
                    @click="loadAssets"
                >
                    Actualizar cuentas
                </button>
            </div>
            <p v-if="accounts.length === 0" class="mt-3 text-sm text-slate-500">
                No se encontraron cuentas en esta conexión.
            </p>
            <div v-else class="mt-4 flex flex-wrap gap-3">
                <div
                    v-for="account in accounts"
                    :key="account.id"
                    class="rounded-lg border border-slate-200 p-3 text-sm"
                >
                    <p class="font-medium">{{ account.name }}</p>
                    <p class="text-slate-500">
                        {{ account.id }} · {{ account.currency }}
                    </p>
                    <button
                        v-if="canManage && !account.linked"
                        type="button"
                        class="mt-2 font-medium text-cyan-700"
                        @click="linkAccount(account)"
                    >
                        Vincular
                    </button>
                    <span
                        v-else-if="account.linked"
                        class="mt-2 block text-emerald-700"
                        >Vinculada</span
                    >
                </div>
            </div>
        </section>

        <section
            v-if="canManage"
            class="mb-6 rounded-xl border border-slate-200 bg-white p-6"
        >
            <h2 class="text-lg font-semibold">Páginas para leads</h2>
            <p class="mt-1 text-sm text-slate-500">
                Los formularios de las páginas vinculadas se importan
                automáticamente al CRM. El token necesita permiso para recuperar
                leads.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <div
                    v-for="page in pages"
                    :key="page.id"
                    class="rounded-lg border border-slate-200 p-3 text-sm"
                >
                    <p class="font-medium">{{ page.name }}</p>
                    <p class="text-slate-500">{{ page.id }}</p>
                    <button
                        v-if="!page.linked"
                        type="button"
                        class="mt-2 font-medium text-cyan-700"
                        @click="linkPage(page)"
                    >
                        Vincular
                    </button>
                    <span v-else class="mt-2 block text-emerald-700"
                        >Vinculada</span
                    >
                </div>
            </div>
            <button
                v-if="pages.some((page) => page.linked)"
                type="button"
                class="mt-4 text-sm font-medium text-cyan-700"
                @click="importLeads"
            >
                Importar leads ahora
            </button>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Consultar campañas</h2>
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <label class="text-sm"
                    >Cuenta<select
                        v-model="selectedAccountId"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
                        @change="loadCampaigns"
                    >
                        <option value="">Selecciona una cuenta</option>
                        <option
                            v-for="account in accounts.filter(
                                (item) => item.linked,
                            )"
                            :key="account.id"
                            :value="account.id"
                        >
                            {{ account.name }}
                        </option>
                    </select></label
                >
                <label class="text-sm"
                    >Desde<input
                        v-model="since"
                        type="date"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
                /></label>
                <label class="text-sm"
                    >Hasta<input
                        v-model="until"
                        type="date"
                        class="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
                /></label>
                <button
                    type="button"
                    :disabled="!selectedAccountId || loading"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    @click="loadCampaigns"
                >
                    Consultar
                </button>
            </div>
            <template v-if="campaigns.length">
                <div
                    class="mt-6 flex flex-wrap items-end justify-between gap-3"
                >
                    <label class="text-sm"
                        >Filtrar visibles<input
                            v-model="search"
                            type="search"
                            placeholder="Nombre de campaña"
                            class="mt-1 block rounded-lg border border-slate-300 px-3 py-2"
                    /></label>
                    <button
                        type="button"
                        :disabled="saving || visibleCampaigns.length === 0"
                        class="rounded-lg bg-cyan-700 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        @click="saveVisible"
                    >
                        Guardar {{ visibleCampaigns.length }} campañas visibles
                    </button>
                </div>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                            <tr>
                                <th class="py-2">Campaña</th>
                                <th>Estado</th>
                                <th>Gasto</th>
                                <th>Impresiones</th>
                                <th>Alcance</th>
                                <th>Clics</th>
                                <th>CTR</th>
                                <th>CPC</th>
                                <th>CPM</th>
                                <th>Guardado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="campaign in visibleCampaigns"
                                :key="campaign.id"
                                class="border-b border-slate-100"
                            >
                                <td class="py-3 font-medium">
                                    {{ campaign.name }}
                                </td>
                                <td>
                                    {{
                                        campaign.effective_status ||
                                        campaign.status ||
                                        '—'
                                    }}
                                </td>
                                <td>
                                    {{ campaign.metrics.spend ?? '—' }}
                                    {{ selectedAccount?.currency }}
                                </td>
                                <td>
                                    {{ campaign.metrics.impressions ?? '—' }}
                                </td>
                                <td>{{ campaign.metrics.reach ?? '—' }}</td>
                                <td>{{ campaign.metrics.clicks ?? '—' }}</td>
                                <td>{{ campaign.metrics.ctr ?? '—' }}</td>
                                <td>{{ campaign.metrics.cpc ?? '—' }}</td>
                                <td>{{ campaign.metrics.cpm ?? '—' }}</td>
                                <td>{{ campaign.saved ? 'Sí' : 'No' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
            <p v-else-if="loading" class="mt-5 text-sm text-slate-500">
                Consultando Meta…
            </p>
            <p
                v-else-if="selectedAccountId"
                class="mt-5 text-sm text-slate-500"
            >
                No hay campañas para esta cuenta.
            </p>
        </section>
    </template>
</template>
