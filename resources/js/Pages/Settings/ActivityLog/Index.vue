<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { FileText, History } from '@lucide/vue';

const props = defineProps({
    activites: Object,
    filtreRecherche: String,
    // Tri serveur courant ({ cle, sens }) ; par défaut Date décroissante.
    tri: { type: Object, default: () => ({ cle: 'date', sens: 'desc' }) },
});

const recherche = ref(props.filtreRecherche ?? '');
const chargement = ref(false);

// Recherche et tri passent par une visite Inertia (liste paginée côté serveur) ;
// la pagination conserve les deux via withQueryString.
const visiter = (tri = props.tri) => {
    router.get(route('settings.activity-log.index'), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
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

const evenementLabel = {
    created: 'Création',
    updated: 'Modification',
    deleted: 'Suppression',
    restored: 'Restauration',
    changement_statut_compte: 'Statut du compte',
    blocage_acces_compte: 'Accès suspendu',
    deblocage_acces_compte: 'Accès rétabli',
};

const evenementVariant = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'warning',
    changement_statut_compte: 'info',
    blocage_acces_compte: 'danger',
    deblocage_acces_compte: 'success',
};

const libelleEvenement = (a) => evenementLabel[a.evenement] ?? a.evenement;
const libelleSujet = (a) => `${a.modele} #${a.sujet_id}`;

const colonnes = [
    { cle: 'date', libelle: 'Date', triable: true, principale: true, priorite: 1, largeurMin: 150 },
    { cle: 'action', libelle: 'Action', triable: true, priorite: 1, largeurMin: 150, tronquer: false, valeur: libelleEvenement },
    { cle: 'sujet', libelle: 'Sur quoi', triable: true, priorite: 2, largeurMin: 170, valeur: libelleSujet },
    { cle: 'auteur', libelle: 'Par qui', triable: true, priorite: 2, largeurMin: 170, valeur: (a) => a.causeur },
];

const total = computed(() => props.activites.total ?? props.activites.data.length);
const compteur = computed(() => `${total.value} activité${total.value > 1 ? 's' : ''}${props.filtreRecherche ? ` trouvée${total.value > 1 ? 's' : ''}` : ''}`);
const messageAucunResultat = computed(() => `Aucune activité ne correspond à « ${props.filtreRecherche} ».`);

// Détail d'une activité : fenêtre modale (lisible à toutes les largeurs).
const detail = ref(null);
</script>

<template>
    <Head title="Journal d'activité" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: `Journal d'activité` }]">
        <template #header>
            <h2 class="flex min-w-0 items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                <History class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                <span class="truncate" title="Journal d'activité">Journal d'activité</span>
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Historique des créations, modifications et suppressions sur les servant(e)s, shifts
                et relèves/permutations de votre organisation.
            </p>

            <SearchInput :model-value="recherche" placeholder="Rechercher par auteur…" label="Rechercher une activité par auteur" @update:model-value="rechercher" />

            <DataTable
                :colonnes="colonnes"
                :lignes="activites.data"
                legende="Journal d'activité de l'organisation"
                mode-tri="serveur"
                :tri="tri"
                :chargement="chargement"
                :filtre-actif="Boolean(filtreRecherche)"
                :compteur="compteur"
                message-vide="Aucune activité enregistrée pour l'instant."
                :message-aucun-resultat="messageAucunResultat"
                libelle-actions="Détail"
                largeur-actions="7.5rem"
                @update:tri="visiter"
            >
                <template #cellule-action="{ ligne }">
                    <Badge :variant="evenementVariant[ligne.evenement] ?? 'neutral'" class="max-w-full">
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ libelleEvenement(ligne) }}</span>
                    </Badge>
                </template>

                <template #actions="{ ligne }">
                    <SecondaryButton
                        class="min-h-[44px]"
                        aria-haspopup="dialog"
                        :aria-label="`Détails de l'activité ${libelleEvenement(ligne)} sur ${libelleSujet(ligne)} du ${ligne.date}`"
                        @click="detail = ligne"
                    >
                        <FileText class="h-4 w-4" aria-hidden="true" />
                        Détails
                    </SecondaryButton>
                </template>
            </DataTable>

            <Pagination :links="activites.links ?? []" label="Pagination du journal d'activité" />
        </div>

        <Modal :show="detail !== null" max-width="2xl" labelledby="titre-detail-activite" @close="detail = null">
            <div v-if="detail" class="p-6">
                <h2 id="titre-detail-activite" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    {{ libelleEvenement(detail) }} — {{ libelleSujet(detail) }}
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Le {{ detail.date }}, par {{ detail.causeur }}
                </p>
                <pre class="mt-4 max-h-[60vh] overflow-y-auto whitespace-pre-wrap break-all rounded-lg bg-neutral-50 p-4 text-xs text-neutral-700 dark:bg-neutral-900 dark:text-neutral-200">{{ JSON.stringify(detail.proprietes, null, 2) }}</pre>
                <div class="mt-6 flex justify-end">
                    <SecondaryButton class="min-h-[44px]" @click="detail = null">Fermer</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
