<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Bot,
    Building2,
    ChevronRight,
    Contact,
    LayoutDashboard,
    Menu,
    Search,
    Settings,
    UserRound,
    Users,
    WalletCards,
    X,
    Clock3,
} from '@lucide/vue';
import { computed, ref } from 'vue';

type NavItem = {
    label: string;
    href: string;
    icon?: unknown;
};

const page = usePage();
const mobileOpen = ref(false);

const user = computed(() => page.props.auth?.user as { name?: string } | undefined);
const userName = computed(() => user.value?.name || 'Luis Robles');
const initials = computed(() =>
    userName.value
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
);

const companyName = 'Acme Marketing';

const crmItems: NavItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Leads', href: '/crm/leads', icon: Contact },
    { label: 'Clientes', href: '/crm/clientes', icon: Users },
    { label: 'Asistente IA', href: '/crm/asistente', icon: Bot },
    { label: 'Configuración', href: '/crm/configuracion', icon: Settings },
];

const payrollItems: NavItem[] = [
    { label: 'Empleados', href: '/pre-nomina/empleados', icon: UserRound },
    { label: 'Asistencias', href: '/pre-nomina/asistencias', icon: Clock3 },
    { label: 'Pre-nómina', href: '/pre-nomina', icon: WalletCards },
];

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (href: string): boolean => {
    if (href === '/dashboard') {
        return currentPath.value === '/dashboard';
    }

    return currentPath.value === href || currentPath.value.startsWith(href + '/');
};

const crmActive = computed(
    () => currentPath.value === '/dashboard' || currentPath.value.startsWith('/crm'),
);
const payrollActive = computed(() => currentPath.value.startsWith('/pre-nomina'));

const closeMobile = (): void => {
    mobileOpen.value = false;
};
</script>

<template>
    <div class="min-h-screen bg-[#f6f8fa] text-slate-900">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[2px] transition-opacity lg:hidden"
            @click="closeMobile"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[232px] flex-col bg-[#0a161e] text-white shadow-2xl shadow-slate-950/10 transition-transform duration-300 ease-out motion-reduce:transition-none lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="flex h-16 items-center justify-between px-5">
                <Link href="/dashboard" class="group inline-flex items-baseline gap-1" @click="closeMobile">
                    <span class="text-[19px] font-semibold tracking-tight transition-colors group-hover:text-slate-100">Nexus</span>
                    <span class="text-[11px] font-semibold tracking-[0.08em] text-[#23c6c8]">ERP</span>
                </Link>
                <button
                    type="button"
                    class="rounded-md p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white lg:hidden"
                    aria-label="Cerrar menú"
                    @click="closeMobile"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div class="px-5 pb-3 text-[10px] font-semibold tracking-[0.16em] text-[#7497ab]">
                OPERACIÓN
            </div>

            <nav class="flex-1 overflow-y-auto px-2.5 pb-6">
                <div class="space-y-1">
                    <div
                        class="flex h-[38px] items-center gap-3 rounded-[7px] px-2.5 transition-colors"
                        :class="crmActive ? 'bg-[#162732]' : 'hover:bg-white/[0.04]'"
                    >
                        <Contact class="size-4" :class="crmActive ? 'text-[#23c6c8]' : 'text-[#96acb9]'" />
                        <span class="text-sm font-semibold" :class="crmActive ? 'text-white' : 'text-[#dce6eb]'">CRM</span>
                    </div>

                    <div class="relative ml-[19px] space-y-0.5 border-l border-[#465b68] py-0.5 pl-[13px]">
                        <Link
                            v-for="item in crmItems"
                            :key="item.href"
                            :href="item.href"
                            class="group relative flex h-[30px] items-center gap-2 rounded-md px-2.5 text-xs transition-all duration-200 motion-reduce:transition-none"
                            :class="
                                isActive(item.href)
                                    ? 'bg-[#162732] font-semibold text-white'
                                    : 'font-medium text-[#96acb9] hover:bg-white/[0.04] hover:text-white'
                            "
                            @click="closeMobile"
                        >
                            <span class="absolute -left-[14px] top-1/2 h-px w-3 bg-[#465b68]" />
                            <component :is="item.icon" class="size-3.5 shrink-0 opacity-90" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>

                <div class="mt-8 space-y-1">
                    <div
                        class="flex h-[38px] items-center gap-3 rounded-[7px] px-2.5 transition-colors"
                        :class="payrollActive ? 'bg-[#162732]' : 'hover:bg-white/[0.04]'"
                    >
                        <WalletCards
                            class="size-4"
                            :class="payrollActive ? 'text-[#23c6c8]' : 'text-[#96acb9]'"
                        />
                        <span
                            class="text-sm"
                            :class="payrollActive ? 'font-semibold text-white' : 'font-medium text-[#dce6eb]'"
                        >
                            PRE-NÓMINA
                        </span>
                    </div>

                    <div class="relative ml-[19px] space-y-0.5 border-l border-[#465b68] py-0.5 pl-[13px]">
                        <Link
                            v-for="item in payrollItems"
                            :key="item.href"
                            :href="item.href"
                            class="group relative flex h-[30px] items-center gap-2 rounded-md px-2.5 text-xs transition-all duration-200 motion-reduce:transition-none"
                            :class="
                                isActive(item.href)
                                    ? 'bg-[#162732] font-semibold text-white'
                                    : 'font-medium text-[#96acb9] hover:bg-white/[0.04] hover:text-white'
                            "
                            @click="closeMobile"
                        >
                            <span class="absolute -left-[14px] top-1/2 h-px w-3 bg-[#465b68]" />
                            <component :is="item.icon" class="size-3.5 shrink-0 opacity-90" />
                            <span>{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="mx-[18px] border-t border-[#22333e] py-6">
                <div class="flex items-center gap-2.5">
                    <div class="flex size-[34px] items-center justify-center rounded-full bg-[#162732] text-[10px] font-semibold text-[#23c6c8] ring-1 ring-white/5">
                        {{ initials }}
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-semibold text-white">{{ userName }}</p>
                        <p class="mt-0.5 truncate text-[10px] text-[#849ba9]">{{ companyName }}</p>
                    </div>
                </div>
                <p class="mt-5 text-[10px] font-medium text-[#5f7887]">NexusERP 2.0</p>
            </div>
        </aside>

        <div class="min-h-screen lg:pl-[232px]">
            <header class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:px-7">
                <button
                    type="button"
                    class="mr-3 rounded-lg border border-slate-200 p-2 text-slate-600 transition hover:bg-slate-50 lg:hidden"
                    aria-label="Abrir menú"
                    @click="mobileOpen = true"
                >
                    <Menu class="size-4" />
                </button>

                <div class="flex min-w-0 items-center gap-2.5">
                    <Building2 class="hidden size-4 text-[#23c6c8] sm:block" />
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold text-slate-900">{{ companyName }}</p>
                        <p class="truncate text-[10px] text-slate-500">Empresa activa</p>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <label class="relative hidden md:block">
                        <Search class="pointer-events-none absolute left-3 top-1/2 size-3.5 -translate-y-1/2 text-slate-400" />
                        <input
                            type="search"
                            placeholder="Buscar en NexusERP..."
                            class="h-9 w-[270px] rounded-lg border border-slate-200 bg-slate-50 pl-9 pr-3 text-[11px] text-slate-700 outline-none transition focus:border-[#23c6c8]/70 focus:bg-white focus:ring-2 focus:ring-[#23c6c8]/10"
                        />
                    </label>

                    <button
                        type="button"
                        class="relative flex size-8 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        aria-label="Notificaciones"
                    >
                        <Bell class="size-4" />
                        <span class="absolute right-1.5 top-1.5 size-1.5 rounded-full bg-[#23c6c8]" />
                    </button>

                    <div class="hidden items-center gap-2 sm:flex">
                        <div class="text-right">
                            <p class="max-w-28 truncate text-[11px] font-semibold text-slate-900">{{ userName }}</p>
                            <p class="text-[9px] text-slate-500">Administrador</p>
                        </div>
                        <div class="flex size-[34px] items-center justify-center rounded-full bg-[#0a161e] text-[10px] font-semibold text-white">
                            {{ initials }}
                        </div>
                    </div>
                </div>
            </header>

            <main class="mx-auto w-full max-w-[1500px] px-4 py-6 md:px-8 md:py-7">
                <div class="animate-in fade-in slide-in-from-bottom-1 duration-300 motion-reduce:animate-none">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
