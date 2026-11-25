<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
import NexusKpiCard from '@/components/nexus/NexusKpiCard.vue';
import NexusPageHeader from '@/components/nexus/NexusPageHeader.vue';

type Account = { id: string; name: string; linked: boolean; currency: string };
type Campaign = {
    id: string;
    name: string;
    effective_status: string | null;
    status: string | null;
    metrics: Record<string, string | number>;
    accountName: string;
    currency: string;
};

type LeadCount = { name: string; count: number };
type LeadStats = {
    total: number;
    byStage: LeadCount[];
    bySource: LeadCount[];
};

const props = defineProps<{
    canViewCrm: boolean;
    savedCampaigns: Campaign[];
    leadStats: LeadStats | null;
}>();
// Meta en vivo es la fuente por default; 'local' usa las copias guardadas en el CRM.
const source = ref<'meta' | 'local'>('meta');
const campaigns = ref<Campaign[]>([]);
const activeCampaigns = computed(() =>
    source.value === 'meta' ? campaigns.value : props.savedCampaigns,
);
const loading = ref(props.canViewCrm);
const error = ref('');
const hasAccounts = ref(false);
const since = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
const until = new Date().toISOString().slice(0, 10);

const sum = (values: (string | number | undefined)[]) =>
    values.reduce<number>((total, value) => total + Number(value ?? 0), 0);
const spend = computed(() =>
    sum(activeCampaigns.value.map((campaign) => campaign.metrics.spend)),
);
const clicks = computed(() =>
    sum(activeCampaigns.value.map((campaign) => campaign.metrics.clicks)),
);
const impressions = computed(() =>
    sum(activeCampaigns.value.map((campaign) => campaign.metrics.impressions)),
);
const currencies = computed(
    () =>
        [
            ...new Set(
                activeCampaigns.value.map((campaign) => campaign.currency),
            ),
        ].join(', ') || '—',
);
const ctr = computed(() =>
    impressions.value
        ? `${((clicks.value / impressions.value) * 100).toFixed(2)}%`
        : '—',
);
const costPerLead = computed(() => {
    const leads = props.leadStats?.total ?? 0;
    return leads && spend.value
        ? (spend.value / leads).toLocaleString('es-MX', {
              maximumFractionDigits: 2,
          })
        : '—';
});
const topCampaigns = computed(() =>
    [...activeCampaigns.value]
        .sort(
            (a, b) =>
                Number(b.metrics.spend ?? 0) - Number(a.metrics.spend ?? 0),
        )
        .slice(0, 5),
);

onMounted(async () => {
    if (!props.canViewCrm) return;
    try {
        const response = await fetch('/crm/meta/ad-accounts', {
            headers: { Accept: 'application/json' },
        });
        if (!response.ok)
            throw new Error(
                (await response.json()).message ||
                    'No se pudieron consultar las cuentas de Meta.',
            );
        const accounts: Account[] = (await response.json()).data.filter(
            (item: Account) => item.linked,
        );
        hasAccounts.value = accounts.length > 0;
        const results = await Promise.allSettled(
            accounts.map(async (account) => {
                const params = new URLSearchParams({
                    account_id: account.id,
                    since,
                    until,
                });
                const result = await fetch(`/crm/meta/campaigns?${params}`, {
                    headers: { Accept: 'application/json' },
                });
                if (!result.ok)
                    throw new Error(
                        (await result.json()).message ||
                            'Meta no devolvió las campañas.',
                    );
                return ((await result.json()).data as Campaign[]).map(
                    (campaign) => ({
                        ...campaign,
                        accountName: account.name,
                        currency: account.currency,
                    }),
                );
            }),
        );
        campaigns.value = results.flatMap((result) =>
            result.status === 'fulfilled' ? result.value : [],
        );
        if (results.some((result) => result.status === 'rejected')) {
            error.value =
                'Algunas cuentas no respondieron. Revisa su conexión en Campañas.';
        }
    } catch (cause) {
        error.value =
            cause instanceof Error ? cause.message : 'Meta no respondió.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <Head title="Dashboard CRM" />
    <NexusPageHeader
        eyebrow="Dashboard / CRM"
        title="Dashboard"
        description="Campañas de Meta de los últimos 30 días."
    />
    <p
        v-if="error"
        role="alert"
        class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"
    >
        {{ error }}
    </p>
    <template v-if="canViewCrm">
        <div
            class="mb-4 inline-flex rounded-lg border border-slate-200 bg-white p-1 text-sm"
        >
            <button
                v-for="option in [
                    { value: 'meta', label: 'Meta en vivo' },
                    { value: 'local', label: 'Guardado en CRM' },
                ]"
                :key="option.value"
                type="button"
                class="rounded-md px-3 py-1.5 font-medium"
                :class="
                    source === option.value
                        ? 'bg-slate-900 text-white'
                        : 'text-slate-600'
                "
                :aria-pressed="source === option.value"
                @click="source = option.value as 'meta' | 'local'"
            >
                {{ option.label }}
            </button>
        </div>
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <NexusKpiCard
                label="Gasto en Meta"
                :value="
                    spend.toLocaleString('es-MX', { maximumFractionDigits: 2 })
                "
                :helper="currencies"
                tone="cyan"
            />
            <NexusKpiCard
                label="Clics"
                :value="clicks.toLocaleString('es-MX')"
                :helper="`CTR ${ctr}`"
                tone="violet"
            />
            <NexusKpiCard
                label="Leads (30 días)"
                :value="leadStats?.total ?? 0"
                helper="Del CRM"
                tone="green"
            />
            <NexusKpiCard
                label="Costo por lead"
                :value="costPerLead"
                helper="Gasto Meta / leads"
                tone="amber"
            />
        </div>
        <div class="mb-6 grid gap-4 lg:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold">Top campañas por gasto</h2>
                <ul v-if="topCampaigns.length" class="mt-3 space-y-2 text-sm">
                    <li
                        v-for="campaign in topCampaigns"
                        :key="campaign.id"
                        class="flex justify-between gap-3"
                    >
                        <span class="truncate">{{ campaign.name }}</span>
                        <span class="shrink-0 font-medium tabular-nums">
                            {{ campaign.metrics.spend ?? '—' }}
                            {{ campaign.currency }}
                        </span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">Sin campañas.</p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold">Leads por etapa</h2>
                <ul
                    v-if="leadStats?.byStage.length"
                    class="mt-3 space-y-2 text-sm"
                >
                    <li
                        v-for="stage in leadStats.byStage"
                        :key="stage.name"
                        class="flex justify-between"
                    >
                        <span>{{ stage.name }}</span>
                        <span class="font-medium tabular-nums">{{
                            stage.count
                        }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">
                    Sin leads en el período.
                </p>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="text-base font-semibold">Leads por fuente</h2>
                <ul
                    v-if="leadStats?.bySource.length"
                    class="mt-3 space-y-2 text-sm"
                >
                    <li
                        v-for="source in leadStats.bySource"
                        :key="source.name"
                        class="flex justify-between"
                    >
                        <span>{{ source.name }}</span>
                        <span class="font-medium tabular-nums">{{
                            source.count
                        }}</span>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-slate-500">
                    Sin leads en el período.
                </p>
            </section>
        </div>
    </template>
    <section
        v-if="canViewCrm && activeCampaigns.length"
        class="rounded-xl border border-slate-200 bg-white p-6"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">Campañas</h2>
            <Link href="/crm/campanas" class="text-sm font-medium text-cyan-700"
                >Ver todas y guardar</Link
            >
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[650px] text-left text-sm">
                <thead class="border-b border-slate-200 text-slate-500">
                    <tr>
                        <th class="py-2">Campaña</th>
                        <th>Cuenta</th>
                        <th>Estado</th>
                        <th>Gasto</th>
                        <th>Clics</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="campaign in activeCampaigns"
                        :key="campaign.id"
                        class="border-b border-slate-100"
                    >
                        <td class="py-3 font-medium">{{ campaign.name }}</td>
                        <td>{{ campaign.accountName }}</td>
                        <td>
                            {{
                                campaign.effective_status ||
                                campaign.status ||
                                '—'
                            }}
                        </td>
                        <td>
                            {{ campaign.metrics.spend ?? '—' }}
                            {{ campaign.currency }}
                        </td>
                        <td>{{ campaign.metrics.clicks ?? '—' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
    <p v-else-if="loading" class="text-sm text-slate-500">
        Consultando campañas de Meta…
    </p>
    <div v-else-if="canViewCrm && !error">
        <NexusEmptyState
            :description="
                hasAccounts
                    ? 'No hay campañas disponibles en las cuentas conectadas.'
                    : 'Vincula una cuenta publicitaria para ver las campañas.'
            "
        />
        <Link
            href="/crm/campanas"
            class="mt-4 inline-block text-sm font-medium text-cyan-700"
            >Ir a campañas</Link
        >
    </div>
    <NexusEmptyState
        v-else-if="!canViewCrm"
        description="Aún no hay información disponible para tu rol."
    />
</template>
