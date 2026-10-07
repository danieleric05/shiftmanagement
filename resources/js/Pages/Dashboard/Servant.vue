<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, usePage } from '@inertiajs/vue3';

defineProps({
    servant: Object,
    affectations: Array,
});

const page = usePage();

const ORDRE_JOURS = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
const jourLabel = (jour) => (jour ? jour.charAt(0).toUpperCase() + jour.slice(1) : '');

// Mes affectations (tri client). Le jour se trie dans l'ordre du calendrier.
const colonnes = [
    { cle: 'shift', libelle: 'Shift', triable: true, principale: true, priorite: 1, largeurMin: 180 },
    { cle: 'jour', libelle: 'Jour', triable: true, priorite: 1, largeurMin: 100, valeur: (a) => jourLabel(a.jour), valeurTri: (a) => ORDRE_JOURS.indexOf(a.jour) },
    { cle: 'horaire', libelle: 'Horaire', triable: true, priorite: 2, largeurMin: 120, valeur: (a) => `${a.heure_debut} - ${a.heure_fin}`, valeurTri: (a) => a.heure_debut },
    { cle: 'poste', libelle: 'Poste', triable: true, priorite: 1, largeurMin: 150 },
    { cle: 'depuis', libelle: 'Depuis le', triable: true, priorite: 3, largeurMin: 110 },
];
</script>

<template>
    <Head title="Mon espace" />

    <AuthenticatedLayout>
        <template #header>
            <span class="block truncate" :title="`Bonjour, ${page.props.auth.user.name}`">Bonjour, {{ page.props.auth.user.name }} 👋</span>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div v-if="!servant" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700">
                Votre compte n'est associé à aucune fiche Servant(e) pour le moment.
                Contactez un administrateur pour lier votre compte.
            </div>

            <template v-else>
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                    <div class="min-w-0">
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Mon profil</p>
                        <p class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">{{ servant.nom_complet }}</p>
                    </div>
                    <StatusBadge :statut="servant.statut" domain="servant" />
                </div>

                <section aria-labelledby="titre-affectations" class="space-y-3">
                    <h3 id="titre-affectations" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Mes affectations</h3>
                    <DataTable
                        :colonnes="colonnes"
                        :lignes="affectations"
                        legende="Mes affectations en cours"
                        :compteur="`${affectations.length} en cours`"
                        message-vide="Vous n'êtes actuellement affecté à aucun poste."
                    />
                </section>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
