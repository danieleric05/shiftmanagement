<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import ListeShiftsTableauDeBord from './Partials/ListeShiftsTableauDeBord.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Gavel } from '@lucide/vue';
import { computed, ref } from 'vue';

// Rôle « Autres » : même tableau de bord, sans formulaire ni lien d'action.
const lectureSeule = computed(() => Boolean(usePage().props.auth.lectureSeule));

const props = defineProps({
    shifts: Array,
    releves: Object,
    permutations: Object,
    appels: Object,
    besoins: Object,
});

const titre = computed(() => (lectureSeule.value ? 'Tableau de bord (consultation)' : 'Tableau de bord des dirigeants'));

const today = () => new Date().toISOString().slice(0, 10);

// ---- Colonnes (tri client : quelques demandes récentes en mémoire) ----
// Résultat / Date : les demandes traitées par date de résultat, les demandes
// en attente (sans résultat) toujours en fin de liste.
const colonneResultat = { cle: 'resultat', libelle: 'Résultat / Date', triable: true, priorite: 1, largeurMin: 150, tronquer: false, valeurTri: (d) => (d.statut === 'traitee' ? d.resultat_date : null) };

const colonnesReleveOuAppel = [
    { cle: 'servant', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 160 },
    { cle: 'shift', libelle: 'Shift', triable: true, priorite: 2, largeurMin: 140 },
    { cle: 'coordonnees', libelle: 'Coordonnées', triable: true, priorite: 4, largeurMin: 130 },
    { cle: 'motif', libelle: 'Raison', triable: true, priorite: 3, largeurMin: 150 },
    { cle: 'date_demande', libelle: 'Date', triable: true, priorite: 2, largeurMin: 110 },
    { cle: 'discussion_servant', libelle: 'Discussion', triable: true, priorite: 5, largeurMin: 150 },
    colonneResultat,
];

const colonnesPermutation = [
    { cle: 'servant', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 160 },
    { cle: 'shift', libelle: 'Shift', triable: true, priorite: 4, largeurMin: 140 },
    { cle: 'trajet', libelle: 'Shift de/à', triable: true, priorite: 2, largeurMin: 190, valeur: (d) => `${d.shift} → ${d.shift_destination ?? '—'}` },
    { cle: 'motif', libelle: 'Raison', triable: true, priorite: 3, largeurMin: 150 },
    { cle: 'date_demande', libelle: 'Date', triable: true, priorite: 2, largeurMin: 110 },
    { cle: 'approuve_deux_shifts', libelle: 'Approuvé 2 shifts', triable: true, priorite: 3, largeurMin: 120, tronquer: false },
    colonneResultat,
];

const sections = computed(() => [
    { cle: 'releve', titre: 'Demandes de relève des servant(e)s', donnees: props.releves, colonnes: colonnesReleveOuAppel },
    { cle: 'permutation', titre: 'Demandes de permutation des servant(e)s', donnees: props.permutations, colonnes: colonnesPermutation },
    { cle: 'appel', titre: "Demandes d'appel des servant(e)s", donnees: props.appels, colonnes: colonnesReleveOuAppel },
]);

const compteur = (n) => `${n} demande${n > 1 ? 's' : ''} récente${n > 1 ? 's' : ''}`;

// ---- Saisie du résultat d'une relève (fenêtre modale, plus d'édition en ligne) ----
const idAStatuer = ref(null);
const aStatuer = computed(() => props.releves.recentes.find((d) => d.id === idAStatuer.value && d.statut !== 'traitee') ?? null);
const resolution = useForm({ resultat: '', resultat_date: today() });

const ouvrirResolution = (d) => {
    resolution.defaults({ resultat: '', resultat_date: today() });
    resolution.reset();
    resolution.clearErrors();
    idAStatuer.value = d.id;
};
const fermerResolution = () => (idAStatuer.value = null);

const resoudre = () => {
    const d = aStatuer.value;
    if (!d) return;
    resolution.patch(route('shift-transfers.resolve', d.id), {
        preserveScroll: true,
        onSuccess: fermerResolution,
    });
};

const classeLienAction = 'inline-flex min-h-[44px] items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700';
const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100';
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>
            <span class="block truncate" :title="titre">{{ titre }}</span>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Shifts : accès direct aux fiches -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Shifts</h3>
                <p class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">Cliquez pour accéder à la fiche du shift.</p>
                <ListeShiftsTableauDeBord :shifts="shifts" route-fiche="shifts.show" />
            </div>

            <h2 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Résumé des actions des servant(e)s</h2>

            <!-- Demandes de relève, de permutation et d'appel -->
            <section
                v-for="s in sections"
                :key="s.cle"
                :aria-labelledby="`titre-${s.cle}`"
                class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 :id="`titre-${s.cle}`" class="min-w-0 text-base font-semibold text-neutral-900 dark:text-neutral-100">
                        {{ s.titre }}
                        <span v-if="s.donnees.en_attente > 0" class="ml-1 text-sm font-normal text-warning-700 dark:text-warning-300">
                            ({{ s.donnees.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link
                        :href="route('shift-transfers.index', { type: s.cle })"
                        class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >
                        Afficher tout →
                    </Link>
                </div>

                <DataTable
                    :colonnes="s.colonnes"
                    :lignes="s.donnees.recentes"
                    :legende="s.titre"
                    :compteur="compteur(s.donnees.recentes.length)"
                    message-vide="Aucune demande enregistrée pour le moment."
                    largeur-actions="7.5rem"
                >
                    <template #cellule-servant="{ ligne }">
                        <span :title="ligne.servant">{{ ligne.servant }}</span>
                    </template>
                    <template #cellule-approuve_deux_shifts="{ ligne }">
                        <Badge :variant="ligne.approuve_deux_shifts ? 'success' : 'warning'">
                            {{ ligne.approuve_deux_shifts ? 'Oui' : 'Non' }}
                        </Badge>
                    </template>
                    <template #cellule-resultat="{ ligne }">
                        <div v-if="ligne.statut === 'traitee'" class="min-w-0">
                            <Badge variant="success" class="max-w-full">
                                <span class="min-w-0 truncate" :title="ligne.resultat">{{ ligne.resultat }}</span>
                            </Badge>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">{{ ligne.resultat_date }}</p>
                        </div>
                        <Badge v-else-if="ligne.suivi_etat" :variant="ligne.suivi_etat.ton" class="max-w-full">
                            <span class="min-w-0 [overflow-wrap:anywhere]">{{ ligne.suivi_etat.libelle }}</span>
                        </Badge>
                        <span v-else class="text-xs text-neutral-500 dark:text-neutral-400">En attente</span>
                    </template>

                    <template v-if="!lectureSeule" #actions="{ ligne }">
                        <template v-if="ligne.statut !== 'traitee'">
                            <button
                                v-if="s.cle === 'releve'"
                                type="button"
                                :class="classeLienAction"
                                :aria-label="`Statuer sur la demande de relève de ${ligne.servant}`"
                                @click="ouvrirResolution(ligne)"
                            >
                                <Gavel class="h-4 w-4" aria-hidden="true" />
                                Statuer
                            </button>
                            <Link
                                v-else
                                :href="route('shift-transfers.index', { type: s.cle })"
                                :class="classeLienAction"
                                :aria-label="`${s.cle === 'permutation' && !ligne.pret_pour_decision ? 'Suivre' : 'Statuer sur'} la demande de ${ligne.servant}`"
                            >
                                {{ s.cle === 'permutation' && !ligne.pret_pour_decision ? 'Suivre →' : 'Statuer →' }}
                            </Link>
                        </template>
                    </template>
                </DataTable>
            </section>

            <!-- Besoins de recrutement -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Besoins de recrutement (2 prochains mois)</h3>
                    <Link
                        :href="route('recruitment.index')"
                        class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >
                        Détails →
                    </Link>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:w-1/2">
                    <div class="rounded-lg bg-neutral-50 p-4 text-center dark:bg-neutral-900">
                        <p class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ besoins.freres_recherches }}</p>
                        <p class="text-xs uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Frères recherchés</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 p-4 text-center dark:bg-neutral-900">
                        <p class="text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ besoins.soeurs_recherchees }}</p>
                        <p class="text-xs uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Sœurs recherchées</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Résultat d'une demande de relève ===== -->
        <Modal v-if="!lectureSeule" :show="aStatuer !== null" max-width="md" labelledby="titre-resolution-releve" @close="fermerResolution">
            <form v-if="aStatuer" class="space-y-4 p-6" @submit.prevent="resoudre">
                <h2 id="titre-resolution-releve" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Relève de {{ aStatuer.servant }}
                </h2>
                <p class="break-words text-sm text-neutral-600 [overflow-wrap:anywhere] dark:text-neutral-400">
                    {{ aStatuer.shift }} · demandée le {{ aStatuer.date_demande }}<template v-if="aStatuer.motif"> · {{ aStatuer.motif }}</template>
                </p>
                <div>
                    <InputLabel for="resolution-resultat" value="Résultat" />
                    <TextInput id="resolution-resultat" v-model="resolution.resultat" type="text" :class="classeChamp" required autocomplete="off" />
                    <InputError class="mt-1" :message="resolution.errors.resultat" />
                </div>
                <div>
                    <InputLabel for="resolution-date" value="Date du résultat" />
                    <TextInput id="resolution-date" v-model="resolution.resultat_date" type="date" :class="classeChamp" required />
                    <InputError class="mt-1" :message="resolution.errors.resultat_date" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerResolution">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="resolution.processing">Valider</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
