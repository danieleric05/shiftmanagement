<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import ListeShiftsTableauDeBord from './Partials/ListeShiftsTableauDeBord.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    shifts: Array,
    permutations: Object,
    besoins: Object,
});

const page = usePage();

// Demandes de permutation récentes (tri client). Résultat : les demandes
// traitées par date de résultat, celles en attente toujours en fin de liste.
const colonnesPermutations = [
    { cle: 'servant', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 170 },
    { cle: 'trajet', libelle: 'Shift de/à', triable: true, priorite: 1, largeurMin: 200, valeur: (d) => `${d.shift} → ${d.shift_destination ?? '—'}` },
    { cle: 'date_demande', libelle: 'Date', triable: true, priorite: 2, largeurMin: 110 },
    { cle: 'resultat', libelle: 'Résultat / État', triable: true, priorite: 1, largeurMin: 160, tronquer: false, valeurTri: (d) => (d.statut === 'traitee' ? d.resultat_date : null) },
];

const compteur = (n) => `${n} demande${n > 1 ? 's' : ''} récente${n > 1 ? 's' : ''}`;
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>
            <span class="block truncate" :title="`Bonjour, ${page.props.auth.user.name}`">Bonjour, {{ page.props.auth.user.name }} 👋</span>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <!-- Shifts : les miens en gestion, les autres en lecture seule -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Shifts</h3>
                <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">Cliquez pour accéder à la fiche du shift — lecture seule pour les shifts que vous ne gérez pas.</p>
                <ListeShiftsTableauDeBord :shifts="shifts" route-fiche="shifts.mine.show" lecture-seule-si-non-gere />
            </div>

            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Résumé des actions des servant(e)s</h2>

            <!-- Demandes de permutation -->
            <section aria-labelledby="titre-permutations" class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 id="titre-permutations" class="min-w-0 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        Demandes de permutation
                        <span v-if="permutations.en_attente > 0" class="ml-1 text-sm font-normal text-warning-700 dark:text-warning-300">
                            ({{ permutations.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link
                        :href="route('shift-transfers.index', { type: 'permutation' })"
                        class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-success-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-success-400"
                    >
                        Voir / créer une demande →
                    </Link>
                </div>
                <DataTable
                    :colonnes="colonnesPermutations"
                    :lignes="permutations.recentes"
                    legende="Demandes de permutation récentes"
                    :compteur="compteur(permutations.recentes.length)"
                    message-vide="Aucune demande enregistrée pour le moment."
                >
                    <template #cellule-servant="{ ligne }">
                        <span :title="ligne.servant">{{ ligne.servant }}</span>
                    </template>
                    <template #cellule-resultat="{ ligne }">
                        <span v-if="ligne.statut === 'traitee'" class="text-neutral-700 [overflow-wrap:anywhere] dark:text-neutral-300">{{ ligne.resultat }} — {{ ligne.resultat_date }}</span>
                        <Badge v-else-if="ligne.suivi_etat" :variant="ligne.suivi_etat.ton" class="max-w-full">
                            <span class="min-w-0 [overflow-wrap:anywhere]">{{ ligne.suivi_etat.libelle }}</span>
                        </Badge>
                        <span v-else class="text-neutral-600 dark:text-neutral-400">En attente</span>
                    </template>
                </DataTable>
            </section>

            <!-- Recrutement -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Besoins de recrutement</h3>
                    <Link
                        :href="route('recruitment.index')"
                        class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-success-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-success-400"
                    >Besoins →</Link>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:w-1/2">
                    <div class="rounded-lg bg-success-50 p-4 text-center dark:bg-success-900/20">
                        <p class="text-3xl font-bold text-success-700 dark:text-success-400">{{ besoins.freres_recherches }}</p>
                        <p class="text-xs uppercase tracking-wide text-success-700/80 dark:text-success-400/80">Frères recherchés</p>
                    </div>
                    <div class="rounded-lg bg-success-50 p-4 text-center dark:bg-success-900/20">
                        <p class="text-3xl font-bold text-success-700 dark:text-success-400">{{ besoins.soeurs_recherchees }}</p>
                        <p class="text-xs uppercase tracking-wide text-success-700/80 dark:text-success-400/80">Sœurs recherchées</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
