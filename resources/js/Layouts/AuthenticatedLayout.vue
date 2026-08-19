<script setup>
import { computed, ref } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { useRoleTheme } from '@/composables/useRoleTheme';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    CalendarClock,
    ChevronDown,
    Gavel,
    LayoutDashboard,
    LineChart,
    MessageCircleQuestion,
    Menu,
    Repeat,
    Settings,
    UserPlus,
    Users,
    UsersRound,
    UserSquare,
    X,
} from '@lucide/vue';

const page = usePage();
const role = computed(() => page.props.auth.role);
const user = computed(() => page.props.auth.user);
const notifications = computed(() => page.props.notifications ?? { non_lues: 0, recentes: [] });
const sidebarOpen = ref(false);
const { isAdmin, isGestionnaire, theme, initials } = useRoleTheme();

const marquerLu = (id) => {
    router.patch(route('notifications.read', id), {}, { preserveScroll: true, preserveState: true });
};

const navItems = computed(() => {
    if (isAdmin.value) {
        return [
            { label: 'Tableau de bord', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
            { label: 'Shifts', href: route('shifts.index'), active: route().current('shifts.*'), icon: CalendarClock },
            { label: 'Membres', href: route('servants.index'), active: route().current('servants.*'), icon: Users },
            { label: 'Modèles de Shift', href: route('shift-templates.index'), active: route().current('shift-templates.*'), icon: UsersRound },
            { label: 'Recrutement', href: route('recruitment.index'), active: route().current('recruitment.*'), icon: UserPlus },
            { label: 'Candidats', href: route('candidates.index'), active: route().current('candidates.*'), icon: UserSquare },
            { label: 'Entretiens', href: route('interviews.index'), active: route().current('interviews.*'), icon: MessageCircleQuestion },
            { label: 'Transferts', href: route('shift-transfers.index'), active: route().current('shift-transfers.*'), icon: Repeat },
            { label: 'Gouvernance', href: route('governance.index'), active: route().current('governance.*'), icon: Gavel },
            { label: 'Rapports', href: route('reports.index'), active: route().current('reports.*'), icon: LineChart },
            { label: 'Paramètres', href: route('settings.index'), active: route().current('settings.*'), icon: Settings },
        ];
    }

    if (isGestionnaire.value) {
        return [
            { label: 'Tableau de bord', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
            { label: 'Recrutement', href: route('recruitment.index'), active: route().current('recruitment.*'), icon: UserPlus },
            { label: 'Candidats', href: route('candidates.index'), active: route().current('candidates.*'), icon: UserSquare },
            { label: 'Entretiens', href: route('interviews.index'), active: route().current('interviews.*'), icon: MessageCircleQuestion },
            { label: 'Transferts', href: route('shift-transfers.index'), active: route().current('shift-transfers.*'), icon: Repeat },
        ];
    }

    return [
        { label: 'Tableau de bord', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
    ];
});

</script>

<template>
    <div class="flex min-h-screen bg-neutral-50">
        <!-- Sidebar (desktop) -->
        <aside class="hidden w-64 shrink-0 flex-col lg:flex" :class="theme.aside">
            <div class="flex items-center gap-3 px-6 py-6">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
                    <UsersRound class="h-5 w-5 text-white" stroke-width="2.25" />
                </div>
                <div>
                    <p class="font-bold leading-tight text-white">Shift Management</p>
                    <p class="text-xs leading-tight" :class="theme.brandSub">Plateforme de gestion des Shifts</p>
                </div>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-2">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                    :class="item.active ? theme.linkActive : theme.linkInactive"
                >
                    <component :is="item.icon" class="h-[18px] w-[18px]" stroke-width="2" />
                    {{ item.label }}
                </Link>
            </nav>
        </aside>

        <!-- Sidebar (mobile drawer) -->
        <div v-if="sidebarOpen" class="fixed inset-0 z-40 lg:hidden">
            <div class="fixed inset-0 bg-neutral-900/50" @click="sidebarOpen = false" />
            <aside class="fixed inset-y-0 left-0 flex w-64 flex-col" :class="theme.aside">
                <div class="flex items-center justify-between px-6 py-6">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
                            <UsersRound class="h-5 w-5 text-white" stroke-width="2.25" />
                        </div>
                        <p class="font-bold text-white">Shift Management</p>
                    </div>
                    <button @click="sidebarOpen = false" class="text-white/80 hover:text-white">
                        <X class="h-5 w-5" />
                    </button>
                </div>
                <nav class="flex-1 space-y-1 px-3 py-2">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition"
                        :class="item.active ? theme.linkActive : theme.linkInactive"
                        @click="sidebarOpen = false"
                    >
                        <component :is="item.icon" class="h-[18px] w-[18px]" stroke-width="2" />
                        {{ item.label }}
                    </Link>
                </nav>
            </aside>
        </div>

        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Top header -->
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-neutral-100 bg-white px-4 lg:px-8">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <button class="text-neutral-600 hover:text-neutral-900 lg:hidden" @click="sidebarOpen = true">
                        <Menu class="h-6 w-6" />
                    </button>
                    <div v-if="$slots.header" class="min-w-0 flex-1 text-lg font-semibold leading-tight text-neutral-900">
                        <slot name="header" />
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Dropdown align="right" width="80">
                        <template #trigger>
                            <button
                                type="button"
                                class="relative rounded-full p-2 text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900"
                                aria-label="Notifications"
                            >
                                <Bell class="h-5 w-5" />
                                <span
                                    v-if="notifications.non_lues > 0"
                                    class="absolute right-1 top-1 flex h-4 w-4 items-center justify-center rounded-full bg-danger text-[10px] font-semibold text-white"
                                >
                                    {{ notifications.non_lues > 9 ? '9+' : notifications.non_lues }}
                                </span>
                            </button>
                        </template>

                        <template #content>
                            <div v-if="notifications.recentes.length === 0" class="px-4 py-3 text-sm text-neutral-600">
                                Aucune notification.
                            </div>
                            <Link
                                v-for="n in notifications.recentes"
                                :key="n.id"
                                :href="n.route ? route(n.route) : '#'"
                                class="block border-b border-neutral-100 px-4 py-3 text-left last:border-b-0 hover:bg-neutral-50"
                                @click="marquerLu(n.id)"
                            >
                                <p class="text-sm font-medium text-neutral-900">{{ n.titre }}</p>
                                <p class="mt-0.5 text-xs text-neutral-600">{{ n.message }}</p>
                                <p class="mt-1 text-xs text-neutral-400">{{ n.date }}</p>
                            </Link>
                        </template>
                    </Dropdown>

                    <Dropdown align="right" width="48">
                        <template #trigger>
                            <button type="button" class="flex items-center gap-2.5 rounded-full py-1 pl-1 pr-2 transition hover:bg-neutral-100">
                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white"
                                    :class="theme.aside"
                                >
                                    {{ initials }}
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block text-sm font-medium leading-tight text-neutral-900">{{ user?.name }}</span>
                                    <span class="block text-xs leading-tight text-neutral-600">{{ theme.roleLabel }}</span>
                                </span>
                                <ChevronDown class="hidden h-4 w-4 text-neutral-600 sm:block" />
                            </button>
                        </template>

                        <template #content>
                            <DropdownLink :href="route('profile.edit')">Mon profil</DropdownLink>
                            <DropdownLink :href="route('logout')" method="post" as="button">Déconnexion</DropdownLink>
                        </template>
                    </Dropdown>
                </div>
            </header>

            <div v-if="page.props.licence?.expired" class="bg-amber-50 px-4 py-2 text-center text-sm font-medium text-amber-800 lg:px-8">
                Licence expirée — mode lecture seule. Contactez votre administrateur pour la renouveler.
            </div>

            <!-- Page content -->
            <main class="flex-1 p-4 lg:p-8">
                <slot />
            </main>
        </div>

        <ConfirmDialog />
    </div>
</template>
