<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head } from '@inertiajs/vue3';
import { FileDown, FileSpreadsheet } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    servantsParStatut: Object,
    remplissageShifts: Array,
    avancementFormation: Object,
});

const JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const joursDisponibles = JOURS.filter((jour) => props.remplissageShifts.some((s) => s.jour === jour));

const { recherche, resultats: remplissageShiftsCherches } = useTableSearch(() => props.remplissageShifts, ['nom']);

const jourFiltre = ref('');
const remplissageShiftsFiltres = computed(() => (jourFiltre.value
    ? remplissageShiftsCherches.value.filter((s) => s.jour === jourFiltre.value)
    : remplissageShiftsCherches.value));

// Tri client sur toutes les colonnes ; le jour se trie dans l'ordre de la semaine.
const colonnes = [
    { cle: 'nom', libelle: 'Shift', triable: true, principale: true, priorite: 1, largeurMin: 200, tronquer: false },
    { cle: 'jour', libelle: 'Jour', triable: true, priorite: 2, largeurMin: 110, valeurTri: (s) => JOURS.indexOf(s.jour) },
    { cle: 'postes_vacants', libelle: 'Postes vacants', triable: true, priorite: 2, largeurMin: 130, valeur: (s) => `${s.postes_vacants} / ${s.postes_total}`, valeurTri: (s) => s.postes_vacants },
    { cle: 'taux_remplissage', libelle: 'Taux', triable: true, priorite: 1, largeurMin: 100, valeur: (s) => (s.taux_remplissage === null ? null : `${s.taux_remplissage}%`), valeurTri: (s) => s.taux_remplissage },
];

// Les lignes n'ont pas d'identifiant : leur position d'origine sert de clé.
const cleLigne = (s) => props.remplissageShifts.indexOf(s);

const statutLabel = {
    recommande: 'Recommandés',
    en_formation: 'Nouveaux',
    actif: 'Anciens',
    suspendu: 'Relevés',
    retire: 'Permutants',
};

const classeLienBouton = 'inline-flex min-h-[44px] items-center gap-2 rounded-md px-4 text-xs font-semibold uppercase tracking-widest transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-800';
</script>

<template>
    <Head title="Rapports" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Rapports' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Rapports
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <section class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6" aria-labelledby="titre-statuts">
                <h3 id="titre-statuts" class="mb-4 text-lg font-medium text-neutral-900 dark:text-neutral-100">Servant(e)s par statut</h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-5">
                    <div v-for="(count, statut) in servantsParStatut" :key="statut" class="min-w-0 rounded-lg bg-neutral-50 p-4 text-center dark:bg-neutral-900">
                        <p class="text-2xl font-bold text-neutral-900 dark:text-neutral-100">{{ count }}</p>
                        <p class="break-words text-xs uppercase text-neutral-600 dark:text-neutral-400">{{ statutLabel[statut] }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <a
                        :href="route('reports.servants.csv')"
                        :class="[classeLienBouton, 'bg-white text-neutral-700 ring-1 ring-neutral-300 hover:bg-neutral-50 dark:bg-neutral-800 dark:text-neutral-200 dark:ring-neutral-600 dark:hover:bg-neutral-700']"
                    >
                        <FileSpreadsheet class="h-4 w-4" aria-hidden="true" />
                        Exporter en Excel (CSV)
                    </a>
                </div>
            </section>

            <section class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6" aria-labelledby="titre-remplissage">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h3 id="titre-remplissage" class="text-lg font-medium text-neutral-900 dark:text-neutral-100">Taux de remplissage des Shifts</h3>
                    <a :href="route('reports.shifts.pdf')" :class="[classeLienBouton, 'bg-primary text-white hover:bg-primary/90']">
                        <FileDown class="h-4 w-4" aria-hidden="true" />
                        Exporter en PDF
                    </a>
                </div>
                <div v-if="remplissageShifts.length > 0" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" label="Rechercher un Shift" />
                    <select
                        v-model="jourFiltre"
                        aria-label="Filtrer par jour"
                        class="min-h-[44px] w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 sm:w-auto"
                    >
                        <option value="">Tous les jours</option>
                        <option v-for="jour in joursDisponibles" :key="jour" :value="jour" class="capitalize">{{ jour }}</option>
                    </select>
                </div>

                <DataTable
                    :colonnes="colonnes"
                    :lignes="remplissageShiftsFiltres"
                    :cle-ligne="cleLigne"
                    legende="Taux de remplissage des Shifts"
                    :filtre-actif="Boolean(recherche || jourFiltre)"
                    :compteur="`${remplissageShiftsFiltres.length} Shift${remplissageShiftsFiltres.length > 1 ? 's' : ''}`"
                    message-vide="Aucun Shift pour le moment."
                    message-aucun-resultat="Aucun Shift ne correspond à ces critères."
                >
                    <template #cellule-nom="{ ligne }">
                        <span class="[overflow-wrap:anywhere]">{{ ligne.nom }}</span>
                    </template>
                    <template #cellule-jour="{ ligne }">
                        <span class="capitalize">{{ ligne.jour }}</span>
                    </template>
                    <template #cellule-taux_remplissage="{ ligne }">
                        <span v-if="ligne.taux_remplissage === null" class="text-neutral-400">—</span>
                        <span v-else :class="ligne.taux_remplissage === 100 ? 'text-success-700 dark:text-success-400' : 'text-warning-700 dark:text-warning-400'">
                            {{ ligne.taux_remplissage }}%
                        </span>
                    </template>
                </DataTable>
            </section>

            <section class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6" aria-labelledby="titre-formation">
                <h3 id="titre-formation" class="mb-4 text-lg font-medium text-neutral-900 dark:text-neutral-100">Avancement du parcours de formation</h3>
                <p class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">
                    {{ avancementFormation.taux_avancement !== null ? avancementFormation.taux_avancement + '%' : '—' }}
                </p>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">
                    {{ avancementFormation.etapes_terminees }} étapes terminées sur {{ avancementFormation.total_etapes }} au total (tous servant(e)s confondus).
                </p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
