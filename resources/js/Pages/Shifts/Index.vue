<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    shifts: Object,
    joursDisponibles: { type: Array, default: () => [] },
    aucunShift: { type: Boolean, default: false },
    filtreRecherche: { type: String, default: '' },
    filtreJour: { type: String, default: null },
    // Tri serveur courant ({ cle, sens }), validé par liste blanche côté serveur.
    tri: { type: Object, default: () => ({ cle: null, sens: 'asc' }) },
});

const jourLabel = (jour) => (jour ? jour.charAt(0).toUpperCase() + jour.slice(1) : '');
const genreLabel = (genre) => (genre === 'soeurs' ? 'Sœurs' : 'Frères');

// Recherche, filtre et tri côté serveur (liste paginée) : debounce 300 ms
// pour la recherche puis visite Inertia ; la pagination conserve le tout
// (withQueryString).
const recherche = ref(props.filtreRecherche ?? '');
const jourFiltre = ref(props.filtreJour ?? '');
const chargement = ref(false);

const visiter = (tri = props.tri) => {
    router.get(route('shifts.index'), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
        ...(jourFiltre.value ? { jour: jourFiltre.value } : {}),
        ...(tri?.cle ? { tri: tri.cle, sens: tri.sens } : {}),
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (chargement.value = true),
        onFinish: () => (chargement.value = false),
    });
};

let rechercheTimeout = null;
const rechercher = (valeur) => {
    recherche.value = valeur;
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(() => visiter(), 300);
};

const changerJour = () => {
    clearTimeout(rechercheTimeout);
    visiter();
};

const filtreApplique = computed(() => Boolean(props.filtreRecherche || props.filtreJour));

const compteur = computed(() => {
    const { from, to, total = 0 } = props.shifts;
    if (from && to) return `Affichage de ${from} à ${to} sur ${total} Shift${total > 1 ? 's' : ''}`;
    return `${total} Shift${total > 1 ? 's' : ''}`;
});

// Clés de tri identiques à la liste blanche du serveur (ShiftController::triShifts).
const colonnes = [
    { cle: 'nom', libelle: 'Shift', triable: true, principale: true, priorite: 1, largeurMin: 220 },
    { cle: 'jour', libelle: 'Jour', triable: true, priorite: 1, largeurMin: 110, valeur: (s) => jourLabel(s.jour) },
    { cle: 'heure', libelle: 'Horaire', triable: true, priorite: 2, largeurMin: 120, valeur: (s) => `${s.heure_debut} – ${s.heure_fin}` },
    { cle: 'genre', libelle: 'Genre', triable: true, priorite: 3, largeurMin: 100, valeur: (s) => genreLabel(s.genre) },
    { cle: 'postes', libelle: 'Postes', priorite: 2, largeurMin: 130, tronquer: false },
];
</script>

<template>
    <Head title="Gestion des Shifts" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Shifts' }]">
        <template #header>
            <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Gestion des Shifts">
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
                <select
                    v-model="jourFiltre"
                    aria-label="Filtrer par jour"
                    class="min-h-[44px] w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 sm:w-auto"
                    @change="changerJour"
                >
                    <option value="">Tous les jours</option>
                    <option v-for="jour in joursDisponibles" :key="jour" :value="jour">{{ jourLabel(jour) }}</option>
                </select>
            </div>

            <div v-if="aucunShift" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700">
                Aucun Shift pour le moment.
            </div>

            <template v-else>
                <DataTable
                    :colonnes="colonnes"
                    :lignes="shifts.data"
                    legende="Shifts de l'organisation"
                    mode-tri="serveur"
                    :tri="tri"
                    :chargement="chargement"
                    :filtre-actif="filtreApplique"
                    :compteur="compteur"
                    message-vide="Aucun Shift pour le moment."
                    message-aucun-resultat="Aucun Shift ne correspond à ces critères."
                    largeur-actions="7.5rem"
                    @update:tri="visiter"
                >
                    <template #cellule-nom="{ ligne }">
                        <Link
                            :href="route('shifts.show', ligne.id)"
                            :title="ligne.nom"
                            class="rounded font-medium text-neutral-900 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-100 dark:hover:text-primary-light"
                        >{{ ligne.nom }}</Link>
                    </template>
                    <template #cellule-genre="{ ligne }">
                        <Badge :variant="ligne.genre === 'soeurs' ? 'info' : 'neutral'">{{ genreLabel(ligne.genre) }}</Badge>
                    </template>
                    <template #cellule-postes="{ ligne }">
                        <Badge v-if="ligne.postes_total === 0" variant="neutral">Aucun poste</Badge>
                        <Badge v-else-if="ligne.postes_vacants === 0" variant="success">Complet</Badge>
                        <Badge v-else variant="warning">{{ ligne.postes_vacants }} vacant(s)</Badge>
                    </template>

                    <template #actions="{ ligne }">
                        <Link
                            :href="route('shifts.show', ligne.id)"
                            class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                            :aria-label="`Voir le Shift ${ligne.nom}`"
                        >
                            <Eye class="h-4 w-4" aria-hidden="true" />
                            Voir
                        </Link>
                    </template>
                </DataTable>

                <Pagination :links="shifts.links ?? []" label="Pagination des Shifts" />
            </template>
        </div>
    </AuthenticatedLayout>
</template>
