<script setup lang="ts">
import NexusSkeleton from '@/components/nexus/NexusSkeleton.vue';

withDefaults(
    defineProps<{
        rows?: number;
        showKpis?: boolean;
    }>(),
    {
        rows: 5,
        showKpis: true,
    },
);
</script>

<template>
    <div class="space-y-5" role="status" aria-label="Cargando pre-nómina...">
        <section
            v-if="showKpis"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <div
                v-for="index in 4"
                :key="`kpi-skeleton-${index}`"
                class="min-h-[116px] rounded-[12px] border border-slate-200/90 bg-white p-5"
            >
                <div class="flex items-start gap-3.5">
                    <NexusSkeleton
                        className="size-[34px] shrink-0"
                        rounded="full"
                    />
                    <div class="flex-1 space-y-2.5">
                        <NexusSkeleton className="h-3 w-20" />
                        <NexusSkeleton className="h-6 w-28" />
                        <NexusSkeleton className="h-2.5 w-24" />
                    </div>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-[12px] border border-slate-200/90 bg-white"
        >
            <div class="border-b border-slate-100 px-6 py-5">
                <NexusSkeleton className="h-4 w-40" />
                <NexusSkeleton className="mt-2 h-3 w-64" />
            </div>

            <div class="px-4 py-3">
                <div
                    class="grid grid-cols-9 gap-4 rounded-lg bg-slate-50/90 px-4 py-3"
                >
                    <NexusSkeleton
                        v-for="col in 9"
                        :key="`header-col-${col}`"
                        className="h-3 w-14"
                    />
                </div>

                <div class="divide-y divide-slate-100">
                    <div
                        v-for="row in rows"
                        :key="`row-skeleton-${row}`"
                        class="grid grid-cols-9 items-center gap-4 px-4 py-4"
                    >
                        <NexusSkeleton className="h-3.5 w-28" />
                        <NexusSkeleton className="h-3 w-8" />
                        <NexusSkeleton className="h-3 w-6" />
                        <NexusSkeleton className="h-3 w-6" />
                        <NexusSkeleton className="h-3 w-10" />
                        <NexusSkeleton className="h-3.5 w-16" />
                        <NexusSkeleton className="h-3.5 w-12" />
                        <NexusSkeleton className="h-3.5 w-16" />
                        <NexusSkeleton
                            className="h-6 w-16"
                            rounded="full"
                        />
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
