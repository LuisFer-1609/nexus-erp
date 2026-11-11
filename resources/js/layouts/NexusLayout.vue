<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    Bot,
    Building2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Clock3,
    Contact,
    LayoutDashboard,
    Menu,
    Search,
    Settings,
    UserRound,
    Users,
    WalletCards,
    X,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { User } from '@/types';
import type { Component } from 'vue';

type NavItem = {
    label: string;
    href: string;
    icon: Component;
};

type NavSection = {
    key: 'crm' | 'payroll';
    label: string;
    icon: Component;
    items: NavItem[];
    paths: string[];
};

const page = usePage();
const mobileOpen = ref(false);
const sidebarCollapsed = ref(false);
const expandedSections = ref<Record<NavSection['key'], boolean>>({
    crm: true,
    payroll: true,
});

const user = computed(() => page.props.auth.user as User);
const userName = computed(() => user.value?.name || 'Usuario');
const initials = computed(() =>
    userName.value
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join(''),
);

const companyName = 'Empresa activa';

const navSections: NavSection[] = [
    {
        key: 'crm',
        label: 'CRM',
        icon: Contact,
        paths: ['/dashboard', '/crm'],
        items: [
            { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
            { label: 'Leads', href: '/crm/leads', icon: Contact },
            { label: 'Clientes', href: '/crm/clientes', icon: Users },
            { label: 'Asistente IA', href: '/crm/asistente', icon: Bot },
            {
                label: 'Configuración',
                href: '/crm/configuracion',
                icon: Settings,
            },
        ],
    },
    {
        key: 'payroll',
        label: 'PRE-NÓMINA',
        icon: WalletCards,
        paths: ['/pre-nomina'],
        items: [
            {
                label: 'Empleados',
                href: '/pre-nomina/empleados',
                icon: UserRound,
            },
            {
                label: 'Asistencias',
                href: '/pre-nomina/asistencias',
                icon: Clock3,
            },
            { label: 'Pre-nómina', href: '/pre-nomina', icon: WalletCards },
        ],
    },
];

const currentPath = computed(() => page.url.split('?')[0]);

const isActive = (href: string): boolean => {
    if (href === '/dashboard' || href === '/pre-nomina') {
        return currentPath.value === href;
    }

    return (
        currentPath.value === href || currentPath.value.startsWith(href + '/')
    );
};

const isSectionActive = (section: NavSection): boolean =>
    section.paths.some((path) =>
        path === '/dashboard'
            ? currentPath.value === path
            : currentPath.value === path ||
              currentPath.value.startsWith(path + '/'),
    );

const closeMobile = (): void => {
    mobileOpen.value = false;
};

const toggleSidebar = (): void => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
};

const toggleSection = (section: NavSection): void => {
    expandedSections.value[section.key] = !expandedSections.value[section.key];
};

onMounted(() => {
    sidebarCollapsed.value =
        localStorage.getItem('nexus-sidebar-collapsed') === 'true';

    const storedSections = localStorage.getItem('nexus-sidebar-sections');
    if (storedSections) {
        try {
            expandedSections.value = {
                ...expandedSections.value,
                ...(JSON.parse(storedSections) as Partial<
                    Record<NavSection['key'], boolean>
                >),
            };
        } catch {
            localStorage.removeItem('nexus-sidebar-sections');
        }
    }
});

watch(sidebarCollapsed, (collapsed) => {
    localStorage.setItem('nexus-sidebar-collapsed', String(collapsed));
});

watch(
    expandedSections,
    (sections) => {
        localStorage.setItem(
            'nexus-sidebar-sections',
            JSON.stringify(sections),
        );
    },
    { deep: true },
);
</script>

<template>
    <div class="min-h-screen bg-[#f6f8fa] text-slate-900">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-[2px] lg:hidden"
            @click="closeMobile"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[232px] flex-col bg-[#0a161e] text-white shadow-2xl shadow-slate-950/10 transition-[width,transform] duration-300 ease-out motion-reduce:transition-none lg:translate-x-0"
            :class="[
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
                sidebarCollapsed ? 'lg:w-[72px]' : 'lg:w-[232px]',
            ]"
        >
            <div
                class="flex h-16 items-center justify-between gap-2"
                :class="
                    sidebarCollapsed ? 'px-5 lg:justify-center lg:px-2' : 'px-5'
                "
            >
                <Link
                    href="/dashboard"
                    class="group inline-flex items-baseline gap-1"
                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                    @click="closeMobile"
                >
                    <span
                        class="text-[19px] font-semibold tracking-tight group-hover:text-slate-100"
                        :class="sidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        Nexus
                    </span>
                    <span
                        class="text-[11px] font-semibold tracking-[0.08em] text-[#23c6c8]"
                        :class="sidebarCollapsed ? 'lg:hidden' : ''"
                    >
                        ERP
                    </span>
                </Link>

                <button
                    type="button"
                    class="hidden size-8 items-center justify-center rounded-lg text-[#96acb9] transition hover:bg-white/[0.06] hover:text-white lg:flex"
                    :aria-label="
                        sidebarCollapsed
                            ? 'Expandir barra lateral'
                            : 'Contraer barra lateral'
                    "
                    :title="
                        sidebarCollapsed
                            ? 'Expandir barra lateral'
                            : 'Contraer barra lateral'
                    "
                    @click="toggleSidebar"
                >
                    <ChevronRight v-if="sidebarCollapsed" class="size-4" />
                    <ChevronLeft v-else class="size-4" />
                </button>

                <button
                    type="button"
                    class="rounded-md p-1.5 text-slate-400 transition hover:bg-white/5 hover:text-white lg:hidden"
                    aria-label="Cerrar menú"
                    @click="closeMobile"
                >
                    <X class="size-4" />
                </button>
            </div>

            <div
                class="px-5 pb-3 text-[10px] font-semibold tracking-[0.16em] text-[#7497ab]"
                :class="sidebarCollapsed ? 'lg:hidden' : ''"
            >
                OPERACIÓN
            </div>

            <nav
                class="flex-1 overflow-y-auto pb-6"
                :class="sidebarCollapsed ? 'lg:px-2' : 'px-2.5'"
            >
                <div
                    v-for="(section, sectionIndex) in navSections"
                    :key="section.label"
                    class="space-y-1"
                    :class="sectionIndex > 0 ? 'mt-2' : ''"
                >
                    <button
                        type="button"
                        class="flex h-[38px] w-full items-center gap-3 rounded-[7px] px-2.5 text-left transition-colors"
                        :class="[
                            isSectionActive(section)
                                ? 'bg-[#162732]'
                                : 'hover:bg-white/[0.04]',
                            sidebarCollapsed ? 'lg:justify-center lg:px-0' : '',
                        ]"
                        :title="section.label"
                        :aria-expanded="expandedSections[section.key]"
                        @click="toggleSection(section)"
                    >
                        <component
                            :is="section.icon"
                            class="size-4 shrink-0"
                            :class="
                                isSectionActive(section)
                                    ? 'text-[#23c6c8]'
                                    : 'text-[#96acb9]'
                            "
                        />
                        <span
                            class="text-sm"
                            :class="[
                                isSectionActive(section)
                                    ? 'font-semibold text-white'
                                    : 'font-medium text-[#dce6eb]',
                                sidebarCollapsed ? 'lg:hidden' : '',
                            ]"
                        >
                            {{ section.label }}
                        </span>
                        <component
                            :is="
                                expandedSections[section.key]
                                    ? ChevronDown
                                    : ChevronRight
                            "
                            class="ml-auto size-3.5 shrink-0 text-[#96acb9] transition-transform"
                            :class="sidebarCollapsed ? 'lg:hidden' : ''"
                        />
                    </button>

                    <Transition
                        enter-active-class="transition duration-200 ease-out"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-150 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="-translate-y-1 opacity-0"
                    >
                        <div
                            v-if="expandedSections[section.key]"
                            class="relative ml-[19px] space-y-0.5 border-l border-[#465b68] py-0.5 pl-[13px]"
                            :class="
                                sidebarCollapsed
                                    ? 'lg:ml-0 lg:border-l-0 lg:pl-0'
                                    : ''
                            "
                        >
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="group relative flex h-[30px] items-center gap-2 rounded-md px-2.5 text-xs transition-all duration-200 motion-reduce:transition-none"
                                :class="[
                                    isActive(item.href)
                                        ? 'bg-[#162732] font-semibold text-white'
                                        : 'font-medium text-[#96acb9] hover:bg-white/[0.04] hover:text-white',
                                    sidebarCollapsed
                                        ? 'lg:h-[38px] lg:justify-center lg:px-0'
                                        : '',
                                ]"
                                :title="item.label"
                                @click="closeMobile"
                            >
                                <span
                                    class="absolute top-1/2 -left-[14px] h-px w-3 bg-[#465b68]"
                                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                />
                                <component
                                    :is="item.icon"
                                    class="size-4 shrink-0 opacity-90"
                                />
                                <span
                                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                                    >{{ item.label }}</span
                                >
                            </Link>
                        </div>
                    </Transition>
                </div>
            </nav>

            <div
                class="border-t border-[#22333e] py-6"
                :class="sidebarCollapsed ? 'mx-[18px] lg:mx-2' : 'mx-[18px]'"
            >
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-left transition-colors hover:bg-white/[0.06] focus-visible:ring-2 focus-visible:ring-[#23c6c8]/70 focus-visible:outline-none"
                            :class="sidebarCollapsed ? 'lg:justify-center' : ''"
                            aria-label="Abrir menú de usuario"
                        >
                            <span
                                class="flex size-[34px] shrink-0 items-center justify-center rounded-full bg-[#162732] text-[10px] font-semibold text-[#23c6c8] ring-1 ring-white/5"
                            >
                                {{ initials }}
                            </span>
                            <span
                                class="min-w-0 flex-1"
                                :class="sidebarCollapsed ? 'lg:hidden' : ''"
                            >
                                <span
                                    class="block truncate text-xs font-semibold text-white"
                                >
                                    {{ userName }}
                                </span>
                                <span
                                    class="mt-0.5 block truncate text-[10px] text-[#849ba9]"
                                >
                                    {{ companyName }}
                                </span>
                            </span>
                            <ChevronDown
                                class="size-3.5 shrink-0 text-[#849ba9]"
                                :class="sidebarCollapsed ? 'lg:hidden' : ''"
                            />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        class="min-w-60"
                        :side="sidebarCollapsed ? 'right' : 'top'"
                        align="end"
                        :side-offset="8"
                    >
                        <UserMenuContent :user="user" />
                    </DropdownMenuContent>
                </DropdownMenu>
                <p
                    class="mt-5 text-[10px] font-medium text-[#5f7887]"
                    :class="sidebarCollapsed ? 'lg:hidden' : ''"
                >
                    NexusERP
                </p>
            </div>
        </aside>

        <div
            class="min-h-screen transition-[padding] duration-300 ease-out motion-reduce:transition-none"
            :class="sidebarCollapsed ? 'lg:pl-[72px]' : 'lg:pl-[232px]'"
        >
            <header
                class="sticky top-0 z-30 flex h-16 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur md:px-7"
            >
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
                        <p
                            class="truncate text-[13px] font-semibold text-slate-900"
                        >
                            {{ companyName }}
                        </p>
                        <p class="truncate text-[10px] text-slate-500">
                            Contexto de trabajo
                        </p>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-3">
                    <label class="relative hidden md:block">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400"
                        />
                        <input
                            type="search"
                            placeholder="Buscar en NexusERP..."
                            class="h-9 w-[270px] rounded-lg border border-slate-200 bg-slate-50 pr-3 pl-9 text-[11px] text-slate-700 transition outline-none focus:border-[#23c6c8]/70 focus:bg-white focus:ring-2 focus:ring-[#23c6c8]/10"
                        />
                    </label>

                    <button
                        type="button"
                        class="relative flex size-8 items-center justify-center rounded-full text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                        aria-label="Notificaciones"
                    >
                        <Bell class="size-4" />
                        <span
                            class="absolute top-1.5 right-1.5 size-1.5 rounded-full bg-[#23c6c8]"
                        />
                    </button>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <button
                                type="button"
                                class="hidden items-center gap-2 rounded-lg px-1.5 py-1 text-left transition-colors hover:bg-slate-100 focus-visible:ring-2 focus-visible:ring-[#23c6c8]/40 focus-visible:outline-none sm:flex"
                                aria-label="Abrir menú de usuario"
                            >
                                <span class="text-right">
                                    <span
                                        class="block max-w-28 truncate text-[11px] font-semibold text-slate-900"
                                    >
                                        {{ userName }}
                                    </span>
                                    <span
                                        class="block text-[9px] text-slate-500"
                                        >Usuario</span
                                    >
                                </span>
                                <span
                                    class="flex size-[34px] items-center justify-center rounded-full bg-[#0a161e] text-[10px] font-semibold text-white"
                                >
                                    {{ initials }}
                                </span>
                                <ChevronDown class="size-3.5 text-slate-400" />
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="min-w-60"
                            align="end"
                            :side-offset="8"
                        >
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </header>

            <main
                class="mx-auto w-full max-w-[1500px] px-4 py-6 md:px-8 md:py-7"
            >
                <div
                    class="animate-in duration-300 fade-in slide-in-from-bottom-1 motion-reduce:animate-none"
                >
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
