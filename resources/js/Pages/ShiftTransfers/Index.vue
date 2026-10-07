<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DataTable from '@/Components/DataTable.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatCard from '@/Components/StatCard.vue';
import Badge from '@/Components/Badge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { ArrowLeftRight, CircleCheck, CircleX, Clock, FolderOpen, Phone, Plus, Repeat, Trash2, UserRound } from '@lucide/vue';

const iconeEtape = { fait: CircleCheck, refuse: CircleX, en_attente: Clock };
const classeEtape = {
    fait: 'text-success-700 dark:text-success-400',
    refuse: 'text-danger dark:text-danger-400',
    en_attente: 'text-warning-700 dark:text-warning-400',
};

const { confirmer } = useConfirm();

const props = defineProps({
    demandes: Object,
    shifts: Array,
    shiftsDestination: { type: Array, default: () => [] },
    servants: Array,
    filtreType: String,
    filtreRecherche: String,
    estAdministrateur: Boolean,
    // Consultation de tous les types (administrateur, secrétaire, rôle « Autres »).
    consulteTout: Boolean,
    compteurs: Object,
    // Tri serveur courant ({ cle, sens }), validé par liste blanche côté serveur.
    // Sans tri : date de demande décroissante.
    tri: { type: Object, default: () => ({ cle: null, sens: 'asc' }) },
});

// Rôle « Autres » : consultation seule — ni création, ni validation, ni résultat, ni suppression.
const lectureSeule = computed(() => Boolean(usePage().props.auth.lectureSeule));
const voitTout = computed(() => props.estAdministrateur || props.consulteTout);

const optionsServants = computed(() => props.servants.map((s) => ({ value: s.id, label: `${s.prenom} ${s.nom}` })));

const typeIcon = {
    releve: Repeat,
    permutation: ArrowLeftRight,
    appel: Phone,
};

const typeLabel = {
    releve: 'Relève',
    permutation: 'Permutation',
    appel: 'Appel',
};

// ---- Création (fenêtre modale) ----
const showCreateForm = ref(false);

const form = useForm({
    shift_id: '',
    // Le coordonnateur d'équipe ne gère que les permutations.
    type: props.estAdministrateur ? 'releve' : 'permutation',
    servant_id: '',
    shift_destination_id: '',
    motif: '',
    // Aujourd'hui par défaut (date locale, pas UTC) : champ obligatoire.
    date_demande: new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10),
    discussion_servant: '',
    approuve_deux_shifts: false,
    notes: '',
});

const ouvrirCreation = () => {
    form.clearErrors();
    showCreateForm.value = true;
};

const creerDemande = () => {
    form.post(route('shift-transfers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

// ---- Recherche, filtre de type et tri : visite Inertia (liste paginée) ----
const recherche = ref(props.filtreRecherche ?? '');
const chargement = ref(false);

const visiter = ({ type = props.filtreType, tri = props.tri } = {}) => {
    router.get(route('shift-transfers.index'), {
        ...(type ? { type } : {}),
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

const filtrer = (type) => {
    clearTimeout(rechercheTimeout);
    visiter({ type });
};

let rechercheTimeout = null;
const rechercherAvecDelai = () => {
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(() => visiter(), 300);
};

const filtresType = computed(() => [
    { valeur: '', libelle: 'Toutes' },
    { valeur: 'releve', libelle: 'Relèves' },
    { valeur: 'permutation', libelle: 'Permutations' },
    { valeur: 'appel', libelle: 'Appels' },
]);

const filtreApplique = computed(() => Boolean(props.filtreRecherche || props.filtreType));

const libelleResultats = computed(() => {
    const total = props.demandes.total ?? props.demandes.data.length;
    return `${total} demande${total > 1 ? 's' : ''} en attente${filtreApplique.value ? ` trouvée${total > 1 ? 's' : ''}` : ''}`;
});

const messageAucunResultat = computed(() => {
    if (props.filtreRecherche) return `Aucune demande ne correspond à « ${props.filtreRecherche} ».`;
    if (props.filtreType) return `Aucune demande de type « ${typeLabel[props.filtreType]} » enregistrée pour l'instant.`;
    return 'Aucune demande ne correspond à ces critères.';
});

const messageVide = computed(() => {
    if (lectureSeule.value) return 'Aucune demande en attente pour l\'instant.';
    if (!props.estAdministrateur) return 'Aucune permutation enregistrée pour l\'instant. Utilisez « Nouvelle demande » pour en créer une.';
    return 'Aucune relève, permutation ni appel enregistré pour l\'instant. Utilisez « Nouvelle demande » pour en créer une.';
});

// ---- Colonnes (priorité : 1 = toujours visible, plus grand = masqué en premier) ----
const colonnes = [
    { cle: 'servant', libelle: 'Servant(e)', triable: true, principale: true, priorite: 1, largeurMin: 170, tronquer: false },
    { cle: 'type', libelle: 'Type', triable: true, priorite: 2, largeur: '9.5rem', largeurMin: 152, tronquer: false, valeur: (d) => typeLabel[d.type] },
    { cle: 'shift', libelle: 'Shift', triable: true, priorite: 2, largeurMin: 170, tronquer: false },
    { cle: 'date_demande', libelle: 'Date de demande', triable: true, priorite: 3, largeur: '8rem', largeurMin: 128 },
    { cle: 'suivi', libelle: 'Suivi', priorite: 4, largeurMin: 150, tronquer: false, valeur: (d) => d.suivi?.etat?.libelle ?? null },
    { cle: 'motif', libelle: 'Motif', priorite: 5, largeurMin: 180 },
    { cle: 'statut', libelle: 'Statut', triable: true, priorite: 4, largeur: '8rem', largeurMin: 128, tronquer: false },
];

// ---- Détail et traitement d'une demande (fenêtre modale) ----
// `updateForms`/`resolveForms` sont indexés par demande et créés à la volée :
// les visites Inertia déclenchées par ces actions (valider/mettre à jour/
// résoudre) préservent l'état local du composant par défaut, donc de
// nouvelles demandes peuvent apparaître dans `demandes.data` (filtre,
// pagination, tri, changement de statut) sans que le composant soit remonté.
// Construire ces formulaires une seule fois au montage laisserait
// `updateForms[d.id]`/`resolveForms[d.id]` undefined pour ces nouvelles
// demandes et ferait planter le rendu (page blanche).
const updateForms = reactive({});
const resolveForms = reactive({});

const assurerFormulaires = (demandesData) => {
    demandesData
        .filter((d) => d.statut === 'en_attente')
        .forEach((d) => {
            if (!updateForms[d.id]) {
                updateForms[d.id] = useForm({
                    discussion_servant: d.discussion_servant ?? '',
                    approuve_deux_shifts: d.approuve_deux_shifts ?? false,
                    entretien_date: d.entretien_date ?? '',
                    entretien_heure: d.entretien_heure ?? '',
                    notes: d.notes ?? '',
                });
            }
            if (!resolveForms[d.id]) {
                resolveForms[d.id] = useForm({ resultat: '', resultat_date: '', favorable: null, shift_position_destination_id: '' });
            }
        });
};

assurerFormulaires(props.demandes.data);
watch(() => props.demandes.data, (demandesData) => assurerFormulaires(demandesData));

// Relue dans les props à chaque réponse : une demande traitée (qui quitte la
// liste) ferme la fenêtre d'elle-même.
const idEnDetail = ref(null);
const enDetail = computed(() => props.demandes.data.find((d) => d.id === idEnDetail.value) ?? null);
const ouvrirDetail = (d) => (idEnDetail.value = d.id);
const fermerDetail = () => (idEnDetail.value = null);

const peutTraiter = (d) => d.statut === 'en_attente' && !lectureSeule.value;
const peutSupprimer = (d) => d.statut === 'en_attente' && !lectureSeule.value && props.estAdministrateur;
const peutDecider = (d) => props.estAdministrateur && (d.type !== 'permutation' || (d.validation_chef_origine && d.validation_chef_destination));
const doitValider = (d) => d.peut_valider_origine || d.peut_valider_destination;
const libelleOuvrir = (d) => (peutTraiter(d) || doitValider(d) ? 'Traiter' : 'Détails');

const mettreAJour = (id) => {
    updateForms[id].patch(route('shift-transfers.update', id), { preserveScroll: true });
};

const resoudre = (demande) => {
    resolveForms[demande.id]
        .transform((data) => (['permutation', 'appel'].includes(demande.type) ? data : { resultat: data.resultat, resultat_date: data.resultat_date }))
        .patch(route('shift-transfers.resolve', demande.id), { preserveScroll: true });
};

const validerOrigine = (id, accepte) => {
    router.patch(route('shift-transfers.valider-origine', id), { accepte }, { preserveScroll: true });
};

const validerDestination = (id, accepte) => {
    router.patch(route('shift-transfers.valider-destination', id), { accepte }, { preserveScroll: true });
};

const supprimer = async (demande) => {
    if (!(await confirmer(`Supprimer cette demande (${demande.servant}) ?`, { danger: true }))) return;
    router.delete(route('shift-transfers.destroy', demande.id), { preserveScroll: true });
};

const actionsDe = (d) => [
    { cle: 'ouvrir', libelle: libelleOuvrir(d), icone: FolderOpen },
    ...(peutSupprimer(d) ? [{ cle: 'supprimer', libelle: 'Supprimer la demande', icone: Trash2, danger: true }] : []),
];

const agir = (d, cle) => ({ ouvrir: ouvrirDetail, supprimer }[cle]?.(d));

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light';
const classeCase = 'h-5 w-5 rounded border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900';
const classeBoutonEtape = 'inline-flex min-h-[44px] items-center rounded-md px-3 text-sm font-medium ring-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light';
</script>

<template>
    <Head title="Modification" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modification' }]">
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
                <h2 class="flex min-w-0 items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    <Repeat class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                    <template v-if="voitTout">Relèves &amp; permutations</template>
                    <template v-else>Permutations</template>
                </h2>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <Link
                        v-if="voitTout"
                        :href="route('shift-transfers.releves')"
                        class="inline-flex min-h-[44px] items-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >
                        Servant(e)s relevé(e)s →
                    </Link>
                    <PrimaryButton v-if="!lectureSeule" type="button" class="min-h-[44px]" @click="ouvrirCreation">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Nouvelle demande
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div v-if="voitTout" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Relèves en attente" :value="compteurs.releves" :icon="Repeat" tone="warning" />
                <StatCard label="Permutations en attente" :value="compteurs.permutations" :icon="ArrowLeftRight" tone="warning" />
                <StatCard label="Appels en attente" :value="compteurs.appels" :icon="Phone" tone="warning" />
            </div>
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Permutations en attente" :value="compteurs.permutations" :icon="ArrowLeftRight" tone="warning" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <SearchInput
                    :model-value="recherche"
                    placeholder="Rechercher un servant(e)…"
                    label="Rechercher une demande par servant(e)"
                    @update:model-value="(v) => { recherche = v; rechercherAvecDelai(); }"
                />

                <div v-if="voitTout" role="group" aria-label="Filtrer par type de demande" class="flex flex-wrap gap-2">
                    <button
                        v-for="f in filtresType"
                        :key="f.valeur"
                        type="button"
                        class="inline-flex min-h-[44px] items-center rounded-full px-4 text-sm font-medium focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                        :class="(filtreType || '') === f.valeur ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700'"
                        :aria-pressed="(filtreType || '') === f.valeur ? 'true' : 'false'"
                        @click="filtrer(f.valeur)"
                    >
                        {{ f.libelle }}
                    </button>
                </div>
            </div>

            <DataTable
                :colonnes="colonnes"
                :lignes="demandes.data"
                legende="Demandes de relève, de permutation et d'appel en attente"
                mode-tri="serveur"
                :tri="tri"
                :chargement="chargement"
                :filtre-actif="filtreApplique"
                :compteur="libelleResultats"
                :message-vide="messageVide"
                :message-aucun-resultat="messageAucunResultat"
                @update:tri="(t) => visiter({ tri: t })"
            >
                <template #cellule-servant="{ ligne, mode }">
                    <span class="[overflow-wrap:anywhere]">{{ ligne.servant }}</span>
                    <span v-if="mode === 'carte' && ligne.coordonnees" class="block text-sm font-normal text-neutral-600 dark:text-neutral-400">{{ ligne.coordonnees }}</span>
                </template>
                <template #cellule-type="{ ligne }">
                    <Badge variant="neutral">
                        <component :is="typeIcon[ligne.type]" class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ typeLabel[ligne.type] }}
                    </Badge>
                </template>
                <template #cellule-shift="{ ligne }">
                    <span class="[overflow-wrap:anywhere]">{{ ligne.shift }}</span>
                    <template v-if="ligne.shift_destination">
                        <span class="text-neutral-500 dark:text-neutral-400"> → </span>
                        <span class="sr-only">vers</span>
                        <span class="[overflow-wrap:anywhere]">{{ ligne.shift_destination }}</span>
                    </template>
                </template>
                <template #cellule-suivi="{ ligne, mode }">
                    <Badge v-if="ligne.suivi" :variant="ligne.suivi.etat.ton" class="max-w-full" :title="ligne.suivi.etat.libelle">
                        <span class="min-w-0 [overflow-wrap:anywhere]" :class="mode === 'tableau' ? 'line-clamp-2' : ''">{{ ligne.suivi.etat.libelle }}</span>
                    </Badge>
                    <span v-else class="text-neutral-400">—</span>
                </template>
                <template #cellule-motif="{ ligne, mode }">
                    <span :class="mode === 'tableau' ? '' : 'line-clamp-2'" :title="ligne.motif">{{ ligne.motif }}</span>
                </template>
                <template #cellule-statut="{ ligne }">
                    <StatusBadge :statut="ligne.statut" />
                </template>

                <template #actions="{ ligne, mode }">
                    <button
                        v-if="mode === 'tableau'"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                        :aria-label="`${libelleOuvrir(ligne)} : demande de ${ligne.servant}`"
                        :title="`${libelleOuvrir(ligne)} : demande de ${ligne.servant}`"
                        @click="ouvrirDetail(ligne)"
                    >
                        <FolderOpen class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <SecondaryButton v-else class="min-h-[44px]" :aria-label="`${libelleOuvrir(ligne)} : demande de ${ligne.servant}`" @click="ouvrirDetail(ligne)">
                        <FolderOpen class="h-4 w-4" aria-hidden="true" />
                        {{ libelleOuvrir(ligne) }}
                    </SecondaryButton>
                    <ActionsMenu
                        v-if="peutSupprimer(ligne)"
                        :libelle="`Autres actions pour la demande de ${ligne.servant}`"
                        :actions="actionsDe(ligne)"
                        @choisir="(cle) => agir(ligne, cle)"
                    />
                </template>
            </DataTable>

            <Pagination :links="demandes.links ?? []" label="Pagination des demandes" />
        </div>

        <!-- ===== Nouvelle demande ===== -->
        <Modal v-if="!lectureSeule" :show="showCreateForm" max-width="2xl" labelledby="titre-nouvelle-demande" @close="showCreateForm = false">
            <form class="p-6" @submit.prevent="creerDemande">
                <h2 id="titre-nouvelle-demande" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Nouvelle demande</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="type" value="Type" />
                        <select id="type" v-model="form.type" :class="classeChamp" required>
                            <option v-if="estAdministrateur" value="releve">Relève</option>
                            <option value="permutation">Permutation</option>
                            <option v-if="estAdministrateur" value="appel">Appel</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.type" />
                    </div>
                    <div>
                        <InputLabel for="date_demande" value="Date de la demande" />
                        <input id="date_demande" v-model="form.date_demande" type="date" :class="classeChamp" required />
                        <InputError class="mt-2" :message="form.errors.date_demande" />
                    </div>
                    <div class="min-w-0">
                        <InputLabel for="shift_id" value="Shift" />
                        <select id="shift_id" v-model="form.shift_id" :class="classeChamp" required>
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.shift_id" />
                    </div>
                    <div v-if="form.type === 'permutation'" class="min-w-0">
                        <InputLabel for="shift_destination_id" value="Shift de destination" />
                        <select id="shift_destination_id" v-model="form.shift_destination_id" :class="classeChamp">
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="s in shiftsDestination" :key="s.id" :value="s.id">{{ s.nom }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.shift_destination_id" />
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <InputLabel for="servant_id" value="Servant(e)" />
                        <SearchableSelect
                            id="servant_id"
                            v-model="form.servant_id"
                            :options="optionsServants"
                            placeholder="Rechercher un servant(e)…"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="form.errors.servant_id" />
                    </div>
                    <div v-if="form.type === 'permutation'" class="flex items-center gap-3 sm:col-span-2">
                        <input id="approuve_deux_shifts" v-model="form.approuve_deux_shifts" type="checkbox" :class="classeCase" />
                        <InputLabel for="approuve_deux_shifts" value="Approuvé par les deux Shifts" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="motif" value="Motif" />
                        <textarea id="motif" v-model="form.motif" rows="2" :class="classeChamp" required></textarea>
                        <InputError class="mt-2" :message="form.errors.motif" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="discussion_servant" value="Discussion avec le servant(e)" />
                        <textarea id="discussion_servant" v-model="form.discussion_servant" rows="2" :class="classeChamp"></textarea>
                        <InputError class="mt-2" :message="form.errors.discussion_servant" />
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="showCreateForm = false">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Soumettre</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- ===== Détail / traitement d'une demande ===== -->
        <Modal :show="enDetail !== null" max-width="2xl" labelledby="titre-detail-demande" @close="fermerDetail">
            <div v-if="enDetail" class="p-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h2 id="titre-detail-demande" class="min-w-0 break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                        {{ typeLabel[enDetail.type] }} — {{ enDetail.servant }}
                    </h2>
                    <StatusBadge :statut="enDetail.statut" />
                </div>

                <div class="mt-3 space-y-2 text-sm text-neutral-600 [overflow-wrap:anywhere] dark:text-neutral-400">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge variant="neutral">
                            <component :is="typeIcon[enDetail.type]" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ typeLabel[enDetail.type] }}
                        </Badge>
                        <span class="min-w-0 rounded-md bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">{{ enDetail.shift }}</span>
                        <template v-if="enDetail.shift_destination">
                            <ArrowLeftRight class="h-3.5 w-3.5 text-neutral-400" aria-hidden="true" />
                            <span class="sr-only">vers</span>
                            <span class="min-w-0 rounded-md bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">{{ enDetail.shift_destination }}</span>
                        </template>
                    </div>
                    <p class="flex flex-wrap items-center gap-1 text-xs text-neutral-500 dark:text-neutral-400">
                        <UserRound class="h-3.5 w-3.5" aria-hidden="true" />
                        Demandé le {{ enDetail.date_demande }} par {{ enDetail.demandeur }}
                        <span v-if="enDetail.coordonnees">· {{ enDetail.coordonnees }}</span>
                    </p>
                    <p><span class="font-medium text-neutral-700 dark:text-neutral-200">Motif :</span> {{ enDetail.motif }}</p>
                    <p v-if="enDetail.discussion_servant">Discussion : {{ enDetail.discussion_servant }}</p>
                    <p v-if="lectureSeule && enDetail.notes">Notes : {{ enDetail.notes }}</p>
                    <p v-if="enDetail.type === 'permutation' && enDetail.approuve_deux_shifts" class="text-xs text-success-700 dark:text-success-400">Approuvé par les deux Shifts</p>
                    <div v-if="enDetail.statut === 'traitee'">
                        <Badge v-if="['permutation', 'appel'].includes(enDetail.type) && enDetail.favorable !== null" :variant="enDetail.favorable ? 'success' : 'danger'" class="mr-1.5">
                            {{ enDetail.favorable ? 'Favorable' : 'Défavorable' }}
                        </Badge>
                        Résultat : {{ enDetail.resultat }} ({{ enDetail.resultat_date }}) — par {{ enDetail.decideur }}
                    </div>
                </div>

                <!-- Suivi de la permutation : état global + étapes -->
                <div v-if="enDetail.type === 'permutation' && enDetail.suivi" class="mt-4 border-t border-neutral-100 pt-4 text-sm dark:border-neutral-700">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-neutral-700 dark:text-neutral-200">Suivi :</span>
                        <Badge :variant="enDetail.suivi.etat.ton">{{ enDetail.suivi.etat.libelle }}</Badge>
                    </div>
                    <ol class="mt-3 space-y-2">
                        <li v-for="etape in enDetail.suivi.etapes" :key="etape.cle" class="flex items-start gap-2">
                            <component :is="iconeEtape[etape.statut]" class="mt-0.5 h-4 w-4 shrink-0" :class="classeEtape[etape.statut]" aria-hidden="true" />
                            <div class="min-w-0 flex-1 [overflow-wrap:anywhere]">
                                <span class="text-neutral-800 dark:text-neutral-100">{{ etape.libelle }}</span>
                                <span v-if="etape.detail" class="text-neutral-500 dark:text-neutral-400"> — {{ etape.detail }}</span>
                                <span v-if="etape.cle === 'origine' && etape.statut === 'en_attente' && enDetail.peut_valider_origine" class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" :class="[classeBoutonEtape, 'text-success-700 ring-success-200 hover:bg-success-50 dark:text-success-400 dark:ring-success-800 dark:hover:bg-neutral-700']" @click="validerOrigine(enDetail.id, true)">Valider</button>
                                    <button type="button" :class="[classeBoutonEtape, 'text-danger ring-danger-200 hover:bg-danger-50 dark:text-danger-400 dark:ring-danger-800 dark:hover:bg-neutral-700']" @click="validerOrigine(enDetail.id, false)">Refuser</button>
                                </span>
                                <span v-if="etape.cle === 'destination' && etape.statut === 'en_attente' && enDetail.peut_valider_destination" class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" :class="[classeBoutonEtape, 'text-success-700 ring-success-200 hover:bg-success-50 dark:text-success-400 dark:ring-success-800 dark:hover:bg-neutral-700']" @click="validerDestination(enDetail.id, true)">Valider</button>
                                    <button type="button" :class="[classeBoutonEtape, 'text-danger ring-danger-200 hover:bg-danger-50 dark:text-danger-400 dark:ring-danger-800 dark:hover:bg-neutral-700']" @click="validerDestination(enDetail.id, false)">Refuser</button>
                                </span>
                            </div>
                        </li>
                    </ol>
                </div>

                <div v-if="peutTraiter(enDetail) && updateForms[enDetail.id]" class="mt-4 grid grid-cols-1 gap-4 border-t border-neutral-100 pt-4 dark:border-neutral-700 sm:grid-cols-2">
                    <div>
                        <InputLabel :for="`discussion-${enDetail.id}`" value="Discussion avec le servant(e)" />
                        <textarea :id="`discussion-${enDetail.id}`" v-model="updateForms[enDetail.id].discussion_servant" rows="2" :class="classeChamp"></textarea>
                    </div>
                    <div>
                        <InputLabel :for="`notes-${enDetail.id}`" value="Notes" />
                        <textarea :id="`notes-${enDetail.id}`" v-model="updateForms[enDetail.id].notes" rows="2" :class="classeChamp"></textarea>
                    </div>
                    <div v-if="enDetail.type === 'permutation'" class="flex items-center gap-3">
                        <input :id="`approuve-${enDetail.id}`" v-model="updateForms[enDetail.id].approuve_deux_shifts" type="checkbox" :class="classeCase" />
                        <InputLabel :for="`approuve-${enDetail.id}`" value="Approuvé par les deux Shifts" />
                    </div>
                    <template v-if="enDetail.type === 'permutation' && estAdministrateur && enDetail.validation_chef_origine && enDetail.validation_chef_destination">
                        <div>
                            <InputLabel :for="`entretien-date-${enDetail.id}`" value="Date de l'entretien" />
                            <input :id="`entretien-date-${enDetail.id}`" v-model="updateForms[enDetail.id].entretien_date" type="date" :class="classeChamp" />
                        </div>
                        <div>
                            <InputLabel :for="`entretien-heure-${enDetail.id}`" value="Heure de l'entretien" />
                            <input :id="`entretien-heure-${enDetail.id}`" v-model="updateForms[enDetail.id].entretien_heure" type="time" :class="classeChamp" />
                        </div>
                    </template>
                    <div class="flex items-end justify-end sm:col-span-2">
                        <SecondaryButton class="min-h-[44px]" :disabled="updateForms[enDetail.id].processing" @click="mettreAJour(enDetail.id)">
                            Enregistrer
                        </SecondaryButton>
                    </div>

                    <template v-if="peutDecider(enDetail)">
                        <div class="border-t border-neutral-100 pt-4 dark:border-neutral-700 sm:col-span-2">
                            <InputLabel :for="`resultat-${enDetail.id}`" value="Résultat" />
                            <textarea :id="`resultat-${enDetail.id}`" v-model="resolveForms[enDetail.id].resultat" rows="2" :class="classeChamp"></textarea>
                            <InputError class="mt-1" :message="resolveForms[enDetail.id].errors.resultat" />
                        </div>
                        <div>
                            <InputLabel :for="`resultat-date-${enDetail.id}`" value="Date du résultat" />
                            <input :id="`resultat-date-${enDetail.id}`" v-model="resolveForms[enDetail.id].resultat_date" type="date" :class="classeChamp" />
                            <InputError class="mt-1" :message="resolveForms[enDetail.id].errors.resultat_date" />
                        </div>

                        <template v-if="['permutation', 'appel'].includes(enDetail.type)">
                            <fieldset>
                                <legend class="block text-sm font-medium text-neutral-700 dark:text-neutral-300">Décision</legend>
                                <div class="mt-1 flex flex-wrap items-center gap-x-4">
                                    <label class="flex min-h-[44px] items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                                        <input v-model="resolveForms[enDetail.id].favorable" type="radio" :name="`favorable-${enDetail.id}`" :value="true" />
                                        Favorable
                                    </label>
                                    <label class="flex min-h-[44px] items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                                        <input v-model="resolveForms[enDetail.id].favorable" type="radio" :name="`favorable-${enDetail.id}`" :value="false" />
                                        Défavorable
                                    </label>
                                </div>
                                <InputError class="mt-1" :message="resolveForms[enDetail.id].errors.favorable" />
                            </fieldset>
                            <div v-if="resolveForms[enDetail.id].favorable === true" class="min-w-0 sm:col-span-2">
                                <InputLabel :for="`poste-destination-${enDetail.id}`" :value="enDetail.type === 'permutation' ? 'Poste sur le shift de destination' : 'Poste sur son shift'" />
                                <select :id="`poste-destination-${enDetail.id}`" v-model="resolveForms[enDetail.id].shift_position_destination_id" :class="classeChamp">
                                    <option value="" disabled>Sélectionner</option>
                                    <option v-for="poste in enDetail.postes_destination_vacants" :key="poste.id" :value="poste.id">{{ poste.nom }}</option>
                                </select>
                                <p v-if="enDetail.postes_destination_vacants.length === 0" class="mt-1 text-xs text-warning-700 dark:text-warning-400">Aucun poste vacant sur ce shift.</p>
                                <InputError class="mt-1" :message="resolveForms[enDetail.id].errors.shift_position_destination_id" />
                            </div>
                        </template>

                        <div class="flex flex-wrap items-end justify-end gap-2 sm:col-span-2">
                            <DangerButton class="min-h-[44px]" @click="supprimer(enDetail)">Supprimer</DangerButton>
                            <SecondaryButton class="min-h-[44px]" :disabled="resolveForms[enDetail.id].processing" @click="resoudre(enDetail)">
                                Enregistrer le résultat
                            </SecondaryButton>
                        </div>
                    </template>
                    <div v-else-if="estAdministrateur && enDetail.type === 'permutation'" class="flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-700 sm:col-span-2">
                        <p class="min-w-0 text-sm italic text-neutral-500 dark:text-neutral-400">
                            En attente de validation par les coordonnateurs d'équipe
                            <template v-if="!enDetail.validation_chef_origine && !enDetail.validation_chef_destination">d'origine et de destination</template>
                            <template v-else-if="!enDetail.validation_chef_origine">d'origine</template>
                            <template v-else>de destination</template>.
                            La décision finale du Conseil sera possible une fois les deux validations faites.
                        </p>
                        <DangerButton class="min-h-[44px]" @click="supprimer(enDetail)">Supprimer</DangerButton>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton class="min-h-[44px]" @click="fermerDetail">Fermer</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
