<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import ReintegrationDialog from '@/Components/ReintegrationDialog.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Repeat, UserRoundPlus } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    releves: Object,
    shiftsReintegration: { type: Array, default: () => [] },
    // Tri serveur courant ({ cle, sens }), validé par liste blanche côté serveur.
    // Sans tri : date de résultat décroissante.
    tri: { type: Object, default: () => ({ cle: null, sens: 'asc' }) },
});

// Servant ciblé par la confirmation de réintégration (Conseil du Temple).
const servantAReintegrer = ref(null);
const ouvrirReintegration = (r) => {
    servantAReintegrer.value = { id: r.servant_id, nom: r.servant, genre: r.genre };
};

// ---- Tri : visite Inertia (liste paginée) ----
const chargement = ref(false);
const trier = (tri) => {
    router.get(route('shift-transfers.releves'), tri?.cle ? { tri: tri.cle, sens: tri.sens } : {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => (chargement.value = true),
        onFinish: () => (chargement.value = false),
    });
};

const libelleResultats = computed(() => {
    const total = props.releves.total ?? props.releves.data.length;
    return `${total} relève${total > 1 ? 's' : ''} traitée${total > 1 ? 's' : ''}`;
});

const texteReintegration = (r) => {
    if (!r.reintegre_le) return null;
    return `Réintégré(e) le ${r.reintegre_le}${r.reintegre_par ? ` par ${r.reintegre_par}` : ''}${r.reintegration_commentaire ? ` — ${r.reintegration_commentaire}` : ''}`;
};

const colonnes = [
    { cle: 'servant', libelle: 'Servant(e)', triable: true, principale: true, priorite: 1, largeurMin: 170, tronquer: false },
    { cle: 'shift', libelle: 'Shift', triable: true, priorite: 2, largeurMin: 150, tronquer: false },
    { cle: 'resultat_date', libelle: 'Date de résultat', triable: true, priorite: 2, largeur: '8rem', largeurMin: 128 },
    { cle: 'decideur', libelle: 'Décidé par', priorite: 4, largeurMin: 140 },
    { cle: 'motif', libelle: 'Motif', priorite: 5, largeurMin: 170 },
    { cle: 'resultat', libelle: 'Résultat', priorite: 5, largeurMin: 170 },
    { cle: 'reintegration', libelle: 'Réintégration', priorite: 3, largeurMin: 150, valeur: texteReintegration },
];

const avecReintegration = computed(() => props.releves.data.some((r) => r.peut_reintegrer));
</script>

<template>
    <Head title="Servant(e)s relevé(e)s" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Changement', href: route('shift-transfers.index') }, { label: 'Servant(e)s relevé(e)s' }]">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <h2 class="flex min-w-0 items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    <Repeat class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                    Servant(e)s relevé(e)s
                </h2>
                <Link
                    :href="route('shift-transfers.index')"
                    class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                >
                    ← Retour aux transferts
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Historique des servant(e)s relevé(e)s de leur poste suite à une demande de relève traitée. Le Conseil peut réintégrer un servant(e) relevé(e) : la relève reste dans cet historique.
            </p>

            <DataTable
                :colonnes="colonnes"
                :lignes="releves.data"
                legende="Servant(e)s relevé(e)s"
                mode-tri="serveur"
                :tri="tri"
                :chargement="chargement"
                :compteur="libelleResultats"
                message-vide="Aucun servant(e) relevé(e) pour l'instant."
                largeur-actions="10.5rem"
                @update:tri="trier"
            >
                <template #cellule-servant="{ ligne, mode }">
                    <span class="[overflow-wrap:anywhere]">{{ ligne.servant }}</span>
                    <span v-if="mode === 'carte' && ligne.coordonnees" class="block text-sm font-normal text-neutral-600 dark:text-neutral-400">{{ ligne.coordonnees }}</span>
                </template>
                <template #cellule-motif="{ ligne, mode }">
                    <span :class="mode === 'tableau' ? '' : 'line-clamp-2'" :title="ligne.motif">{{ ligne.motif || '—' }}</span>
                </template>
                <template #cellule-resultat="{ ligne, mode }">
                    <span :class="mode === 'tableau' ? '' : 'line-clamp-2'" :title="ligne.resultat">{{ ligne.resultat || '—' }}</span>
                </template>
                <template #cellule-reintegration="{ ligne }">
                    <span v-if="ligne.reintegre_le" class="line-clamp-2 text-emerald-800 dark:text-emerald-200" :title="texteReintegration(ligne)">{{ texteReintegration(ligne) }}</span>
                    <span v-else class="text-neutral-400">—</span>
                </template>

                <template v-if="avecReintegration" #actions="{ ligne }">
                    <button
                        v-if="ligne.peut_reintegrer"
                        type="button"
                        class="inline-flex min-h-[44px] items-center gap-1.5 rounded-md bg-primary px-3 text-sm font-semibold text-white hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-800"
                        :aria-label="`Réintégrer ${ligne.servant}`"
                        @click="ouvrirReintegration(ligne)"
                    >
                        <UserRoundPlus class="h-4 w-4" aria-hidden="true" />
                        Réintégrer
                    </button>
                </template>
            </DataTable>

            <Pagination :links="releves.links ?? []" label="Pagination des relèves" />
        </div>

        <ReintegrationDialog
            :show="servantAReintegrer !== null"
            :servant="servantAReintegrer"
            :shifts="shiftsReintegration"
            @close="servantAReintegrer = null"
        />
    </AuthenticatedLayout>
</template>
