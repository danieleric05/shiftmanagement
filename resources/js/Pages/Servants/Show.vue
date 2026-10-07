<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import ParcoursIntegration from '@/Components/ParcoursIntegration.vue';
import ReintegrationDialog from '@/Components/ReintegrationDialog.vue';
import SuppressionServantDialog from '@/Components/SuppressionServantDialog.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { useRoleTheme } from '@/composables/useRoleTheme';
import { Pencil } from '@lucide/vue';

const props = defineProps({
    servant: Object,
    compte: Object,
    etapes: Array,
    etapesDisponibles: Array,
    historique: Array,
    // Relèves traitées (avec réintégration éventuelle) : historique conservé.
    releves: { type: Array, default: () => [] },
    estReleve: { type: Boolean, default: false },
    // Réintégration et suppression définitive : Conseil du Temple uniquement
    // (calculé côté serveur par ServantPolicy).
    peutReintegrer: { type: Boolean, default: false },
    shiftsReintegration: { type: Array, default: () => [] },
    suppression: { type: Object, default: null },
    // Changement manuel du statut (Recommandé / Nouveau / Ancien) : Conseil du
    // Temple uniquement, jamais pour un servant relevé ou permutant.
    peutChangerStatut: { type: Boolean, default: false },
    statutsModifiables: { type: Array, default: () => [] },
});

const reintegrationOuverte = ref(false);
const suppressionOuverte = ref(false);

const { confirmer } = useConfirm();

const { isAdmin, isLectureSeule } = useRoleTheme();

// Compte de connexion, export et anonymisation : réservés à l'administrateur
// (le secrétaire n'y a pas accès, cf. ServantPolicy).
const onglets = computed(() => isAdmin.value
    ? ['Informations', 'Situation', 'Parcours', 'Historique', 'Compte', 'Confidentialité']
    : ['Informations', 'Situation', 'Parcours', 'Historique']);
const ongletActif = ref('Informations');

const compteForm = useForm({
    email: '',
    password: '',
});

const creerCompte = () => {
    compteForm.post(route('servants.account.store', props.servant.id), {
        preserveScroll: true,
        onSuccess: () => compteForm.reset(),
    });
};

const revoquerCompte = async () => {
    if (!(await confirmer('Révoquer ce compte de connexion ? Le servant(e) ne pourra plus se connecter.', { danger: true }))) return;
    router.delete(route('servants.account.destroy', props.servant.id), { preserveScroll: true });
};

const anonymiser = async () => {
    if (!(await confirmer(
        `Anonymiser définitivement les données personnelles de ${props.servant.prenom} ${props.servant.nom} ? Le nom, la photo, le téléphone et l'adresse seront effacés. Son historique d'affectations est conservé mais dissocié de son identité. Cette action est irréversible.`,
        { danger: true },
    ))) return;
    router.patch(route('servants.anonymize', props.servant.id));
};

const parcoursTerminees = computed(() => props.etapes.filter((e) => e.statut === 'termine').length);

const statutForm = useForm({ statut: props.servant.statut });
const changerStatut = async () => {
    const choix = props.statutsModifiables.find((s) => s.value === statutForm.statut);
    if (!choix || statutForm.statut === props.servant.statut) return;
    if (!(await confirmer(`Passer ${props.servant.prenom} ${props.servant.nom} au statut « ${choix.label} » ?`))) {
        statutForm.statut = props.servant.statut;
        return;
    }
    statutForm.patch(route('servants.statut.update', props.servant.id), {
        preserveScroll: true,
        onError: () => { statutForm.statut = props.servant.statut; },
    });
};

// Historique (onglet) : tableaux triables côté client, dates au format AAAA-MM-JJ.
const colonnesHistorique = [
    { cle: 'poste', libelle: 'Poste', triable: true, principale: true, priorite: 1, largeurMin: 170 },
    { cle: 'shift', libelle: 'Shift', triable: true, priorite: 1, largeurMin: 150 },
    { cle: 'date_debut', libelle: 'Début', triable: true, priorite: 2, largeurMin: 120, largeur: '7.5rem' },
    { cle: 'date_fin', libelle: 'Fin', triable: true, priorite: 2, largeurMin: 120, largeur: '7.5rem' },
];

const libelleReintegration = (r) => [
    `Réintégré(e) le ${r.reintegre_le}`,
    r.reintegre_par ? ` par ${r.reintegre_par}` : '',
    r.reintegration_commentaire ? ` — ${r.reintegration_commentaire}` : '',
].join('');

const colonnesReleves = [
    { cle: 'shift', libelle: 'Shift', triable: true, principale: true, priorite: 1, largeurMin: 150 },
    { cle: 'resultat_date', libelle: 'Relevé(e) le', triable: true, priorite: 1, largeurMin: 120, largeur: '7.5rem' },
    { cle: 'decideur', libelle: 'Par', triable: true, priorite: 3, largeurMin: 130 },
    { cle: 'motif', libelle: 'Motif', triable: true, priorite: 4, largeurMin: 160 },
    { cle: 'reintegre_le', libelle: 'Réintégration', triable: true, priorite: 2, largeurMin: 160 },
];

const demarrerParcoursForm = useForm({});
const demarrerParcours = () => {
    demarrerParcoursForm.post(route('servants.workflow.demarrer', props.servant.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Servant(e)s', href: route('servants.index') }, { label: `${servant.prenom} ${servant.nom}` }]">
        <template #header>
            <!-- En-tête de hauteur fixe (h-16) : le nom passe sur deux lignes
                 au plus puis s'abrège (nom complet dans title) ; sous sm,
                 « Modifier » devient une icône pour laisser la place au nom. -->
            <div class="flex min-w-0 items-center justify-between gap-2 sm:gap-3">
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <img
                        v-if="servant.a_photo"
                        :src="route('servants.photo', servant.id)"
                        alt="Photo"
                        class="h-8 w-8 shrink-0 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700 sm:h-10 sm:w-10"
                    />
                    <h2 class="line-clamp-2 min-w-0 break-words text-base font-semibold leading-tight text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100 sm:text-xl" :title="`${servant.prenom} ${servant.nom}`">
                        {{ servant.prenom }} {{ servant.nom }}
                    </h2>
                </div>
                <Link
                    v-if="!isLectureSeule"
                    :href="route('servants.edit', servant.id)"
                    class="inline-flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light sm:px-2"
                >
                    <Pencil aria-hidden="true" class="h-5 w-5 sm:hidden" />
                    <span class="sr-only sm:not-sr-only">Modifier</span><span class="sr-only"> la fiche de {{ servant.prenom }} {{ servant.nom }}</span>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="border-b border-neutral-100 dark:border-neutral-700 px-6">
                    <nav class="-mb-px flex flex-wrap gap-x-6">
                        <button
                            v-for="onglet in onglets"
                            :key="onglet"
                            @click="ongletActif = onglet"
                            class="border-b-2 px-1 py-4 text-sm font-medium"
                            :class="ongletActif === onglet
                                ? 'border-primary text-primary'
                                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100'"
                        >
                            {{ onglet }}
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Informations personnelles -->
                    <dl v-if="ongletActif === 'Informations'" class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Prénom</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.prenom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Nom</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.nom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Genre</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.genre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Téléphone</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.telephone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Téléphone (appel)</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.telephone_appel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Pieu</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.pieu ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Date d'appel</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.date_appel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Date de début</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.date_debut ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Adresse</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.adresse ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Titre de leadership</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.titre_leadership ?? '—' }}</dd>
                        </div>
                    </dl>

                    <!-- Situation actuelle -->
                    <div v-if="ongletActif === 'Situation'" class="space-y-6">
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Statut actuel</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <StatusBadge :statut="servant.statut" domain="servant" />
                                <Badge v-if="estReleve" variant="warning">Relevé(e)</Badge>
                            </dd>
                        </div>

                        <div v-if="peutChangerStatut" class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <InputLabel for="changer-statut" value="Changer le statut" />
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <select
                                    id="changer-statut"
                                    v-model="statutForm.statut"
                                    class="block rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 text-sm shadow-sm"
                                    :disabled="statutForm.processing"
                                    @change="changerStatut"
                                >
                                    <option v-for="option in statutsModifiables" :key="option.value" :value="option.value">{{ option.label }}</option>
                                </select>
                                <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                    Réservé au Conseil du Temple. Le parcours d'intégration n'a aucun effet sur le statut.
                                </span>
                            </div>
                            <InputError class="mt-2" :message="statutForm.errors.statut" />
                        </div>

                        <div v-if="estReleve || peutReintegrer" class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Relève</dt>
                            <dd class="mt-1 flex flex-wrap items-center gap-3">
                                <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                    {{ estReleve
                                        ? "Ce servant(e) a été relevé(e) de son poste. L'historique de la relève est conservé dans l'onglet Historique."
                                        : 'Ce servant(e) est au statut « Permutant ». La réintégration le remet au statut « Ancien ».' }}
                                </span>
                                <PrimaryButton v-if="peutReintegrer" type="button" @click="reintegrationOuverte = true">
                                    Réintégrer
                                </PrimaryButton>
                            </dd>
                        </div>

                        <div class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Parcours d'intégration</dt>
                            <dd class="mt-1">
                                <div v-if="etapes.length > 0" class="flex items-center gap-3">
                                    <span class="text-neutral-900 dark:text-neutral-100">{{ parcoursTerminees }} / {{ etapes.length }} étapes terminées</span>
                                    <button type="button" class="text-sm font-medium text-primary-light hover:text-primary" @click="ongletActif = 'Parcours'">
                                        Voir le détail →
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-3">
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">Aucun parcours démarré pour ce servant(e).</span>
                                    <PrimaryButton v-if="!isLectureSeule" :disabled="demarrerParcoursForm.processing" @click="demarrerParcours">
                                        Démarrer le parcours
                                    </PrimaryButton>
                                </div>
                            </dd>
                        </div>
                    </div>

                    <!-- Parcours d'intégration -->
                    <ParcoursIntegration
                        v-if="ongletActif === 'Parcours'"
                        :servant-id="servant.id"
                        :etapes="etapes"
                        :etapes-disponibles="etapesDisponibles"
                        :lecture-seule="isLectureSeule"
                    />

                    <!-- Historique -->
                    <div v-if="ongletActif === 'Historique'" class="space-y-8">
                        <section aria-labelledby="titre-historique-affectations">
                            <h4 id="titre-historique-affectations" class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Affectations</h4>
                            <DataTable
                                :colonnes="colonnesHistorique"
                                :lignes="historique"
                                legende="Historique des affectations"
                                :tri="{ cle: 'date_debut', sens: 'desc' }"
                                :compteur="`${historique.length} affectation${historique.length > 1 ? 's' : ''}`"
                                message-vide="Aucune affectation pour le moment."
                            />
                        </section>

                        <section v-if="releves.length > 0" aria-labelledby="titre-releves">
                            <h4 id="titre-releves" class="mb-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">Relèves et réintégrations</h4>
                            <DataTable
                                :colonnes="colonnesReleves"
                                :lignes="releves"
                                legende="Relèves et réintégrations"
                                :tri="{ cle: 'resultat_date', sens: 'desc' }"
                                :compteur="`${releves.length} relève${releves.length > 1 ? 's' : ''}`"
                            >
                                <template #cellule-reintegre_le="{ ligne }">
                                    <span v-if="ligne.reintegre_le" class="text-emerald-700 dark:text-emerald-300" :title="libelleReintegration(ligne)">{{ libelleReintegration(ligne) }}</span>
                                    <span v-else class="text-neutral-500 dark:text-neutral-400">Non réintégré(e)</span>
                                </template>
                            </DataTable>
                        </section>
                    </div>

                    <!-- Compte de connexion -->
                    <div v-if="ongletActif === 'Compte'">
                        <div v-if="compte" class="space-y-4">
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                Ce servant(e) dispose d'un compte de connexion : <strong>{{ compte.email }}</strong>
                            </p>
                            <DangerButton @click="revoquerCompte">Révoquer le compte</DangerButton>
                        </div>
                        <form v-else @submit.prevent="creerCompte" class="max-w-md space-y-4">
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                Créer un compte permet à ce servant(e) de se connecter et de voir ses propres affectations.
                            </p>
                            <div>
                                <InputLabel for="compte_email" value="Email" />
                                <TextInput id="compte_email" v-model="compteForm.email" type="email" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="compteForm.errors.email" />
                            </div>
                            <div>
                                <InputLabel for="compte_password" value="Mot de passe" />
                                <TextInput id="compte_password" v-model="compteForm.password" type="password" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="compteForm.errors.password" />
                            </div>
                            <PrimaryButton :disabled="compteForm.processing">Créer le compte</PrimaryButton>
                        </form>
                    </div>

                    <!-- Confidentialité (RGPD) -->
                    <div v-if="ongletActif === 'Confidentialité'" class="max-w-xl space-y-6">
                        <div>
                            <h4 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Droit d'accès et de portabilité</h4>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Exporter l'ensemble des données personnelles détenues sur ce servant(e) (identité, parcours, historique d'affectations) au format JSON.
                            </p>
                            <a :href="route('servants.export', servant.id)" class="mt-3 inline-block">
                                <PrimaryButton type="button">Exporter les données</PrimaryButton>
                            </a>
                        </div>

                        <div class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <h4 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Droit à l'effacement</h4>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Anonymise le nom, la photo, le téléphone et l'adresse de ce servant. Son dossier et son historique d'affectations sont conservés (dissociés de son identité) pour l'intégrité des données de l'organisation. Ses affectations actives sont terminées et son éventuel compte de connexion est révoqué.
                            </p>
                            <DangerButton class="mt-3" @click="anonymiser">Anonymiser (RGPD)</DangerButton>
                        </div>

                        <div v-if="suppression" class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <h4 class="text-sm font-semibold text-red-700 dark:text-red-400">Suppression définitive</h4>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Pour corriger une erreur de saisie (ex. un membre du Conseil inscrit par erreur comme servant) : efface la fiche, la photo, les affectations, le parcours et l'historique de relèves/permutations. Irréversible. Un compte de connexion lié est conservé.
                            </p>
                            <DangerButton class="mt-3" @click="suppressionOuverte = true">Supprimer définitivement</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ReintegrationDialog
            v-if="peutReintegrer"
            :show="reintegrationOuverte"
            :servant="{ id: servant.id, nom: `${servant.prenom} ${servant.nom}`, genre: servant.genre }"
            :shifts="shiftsReintegration"
            @close="reintegrationOuverte = false"
        />
        <SuppressionServantDialog
            v-if="suppression"
            :show="suppressionOuverte"
            :servant="servant"
            :suppression="suppression"
            @close="suppressionOuverte = false"
        />
    </AuthenticatedLayout>
</template>
