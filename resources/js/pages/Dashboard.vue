<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import NexusEmptyState from '@/components/nexus/NexusEmptyState.vue';
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

const props = defineProps<{ canViewCrm: boolean }>();
const campaigns = ref<Campaign[]>([]);
const loading = ref(props.canViewCrm);
const error = ref('');
const hasAccounts = ref(false);
const since = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
const until = new Date().toISOString().slice(0, 10);

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
    <section
        v-if="canViewCrm && campaigns.length"
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
                        v-for="campaign in campaigns"
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
                    : 'Conecta un Business Manager para ver las campañas.'
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
