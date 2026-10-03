<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { libellePagination } from '@/composables/usePagination';
import SearchInput from '@/Components/SearchInput.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shifts: Object,
    joursDisponibles: { type: Array, default: () => [] },
    aucunShift: { type: Boolean, default: false },
    filtreRecherche: { type: String, default: '' },
    filtreJour: { type: String, default: null },
});

const jourLabel = (jour) => jour.charAt(0).toUpperCase() + jour.slice(1);

// Recherche et filtre côté serveur (liste paginée), même pattern que la page
// des comptes : debounce 300 ms puis visite Inertia.
const recherche = ref(props.filtreRecherche ?? '');
const jourFiltre = ref(props.filtreJour ?? '');

const filtrer = () => {
    router.get(route('shifts.index'), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
        ...(jourFiltre.value ? { jour: jourFiltre.value } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let rechercheTimeout = null;
const rechercher = (valeur) => {
    recherche.value = valeur;
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(filtrer, 300);
};

const changerJour = () => {
    clearTimeout(rechercheTimeout);
    filtrer();
};

const shiftsFreres = computed(() => props.shifts.data.filter((s) => s.genre === 'freres'));
const shiftsSoeurs = computed(() => props.shifts.data.filter((s) => s.genre === 'soeurs'));
</script>

<template>
    <Head title="Gestion des Shifts" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Shifts' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Gestion des Shifts
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div v-if="!aucunShift" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    :model-value="recherche"
                    placeholder="Rechercher un Shift…"
                    label="Rechercher un Shift par nom"
                    @update:model-value="rechercher"
                />
                <select v-model="jourFiltre" aria-label="Filtrer par jour" @change="changerJour" class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les jours</option>
                    <option v-for="jour in joursDisponibles" :key="jour" :value="jour">{{ jourLabel(jour) }}</option>
                </select>
            </div>

            <div v-if="aucunShift" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Aucun Shift pour le moment.
            </div>
            <div v-else-if="shifts.data.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Aucun Shift ne correspond à ces critères.
            </div>
            <div v-else class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Frères</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsFreres"
                                :key="shift.id"
                                :href="route('shifts.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700"
                            >
                                <span class="capitalize text-neutral-900 dark:text-neutral-100">{{ shift.jour }} — {{ shift.nom }}</span>
                                <Badge v-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                <Badge v-else-if="shift.postes_vacants === 0" variant="success">Voir</Badge>
                                <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                            </Link>
                            <p v-if="shiftsFreres.length === 0" class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400">Aucun Shift.</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Sœurs</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsSoeurs"
                                :key="shift.id"
                                :href="route('shifts.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-700"
                            >
                                <span class="capitalize text-neutral-900 dark:text-neutral-100">{{ shift.jour }} — {{ shift.nom }}</span>
                                <Badge v-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                <Badge v-else-if="shift.postes_vacants === 0" variant="success">Voir</Badge>
                                <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                            </Link>
                            <p v-if="shiftsSoeurs.length === 0" class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400">Aucun Shift.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="shifts.total > 0" class="flex flex-col items-center gap-3">
                <p v-if="shifts.from && shifts.to" class="text-sm text-neutral-600 dark:text-neutral-400" role="status" aria-live="polite">
                    Affichage de {{ shifts.from }} à {{ shifts.to }} sur {{ shifts.total }} Shift{{ shifts.total > 1 ? 's' : '' }}
                </p>
                <nav v-if="shifts.links?.length > 3" aria-label="Pagination des Shifts" class="flex flex-wrap justify-center gap-1">
                    <template v-for="link in shifts.links" :key="link.label">
                        <span
                            v-if="!link.url"
                            class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                            v-html="libellePagination(link.label)"
                        />
                        <Link
                            v-else
                            :href="link.url"
                            preserve-scroll
                            preserve-state
                            :aria-current="link.active ? 'page' : undefined"
                            class="rounded-md px-3 py-1.5 text-sm"
                            :class="link.active ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700'"
                            v-html="libellePagination(link.label)"
                        />
                    </template>
                </nav>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
