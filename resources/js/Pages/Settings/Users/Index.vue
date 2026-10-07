<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import TextInput from '@/Components/TextInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Link2Off, Pencil, Plus, Trash2, X } from '@lucide/vue';
import { computed, ref } from 'vue';

const { confirmer } = useConfirm();

const props = defineProps({
    users: Object,
    roles: Array,
    shifts: Array,
    filtreRecherche: { type: String, default: '' },
    filtreRole: { type: Number, default: null },
    filtreStatut: { type: String, default: null },
    filtreAcces: { type: String, default: null },
    // Tri serveur courant ({ cle, sens }), validé par liste blanche côté serveur.
    tri: { type: Object, default: () => ({ cle: null, sens: 'asc' }) },
    // Statuts de la personne (Recommandé / Nouveau / Ancien), libellés fournis
    // par le serveur (User::libellesStatut(), repris des servants).
    statuts: { type: Array, default: () => [] },
});

const moi = computed(() => usePage().props.auth?.user?.id);

const optionsShifts = computed(() => props.shifts.map((s) => ({ value: s.id, label: s.nom })));
const roleDe = (roleId) => props.roles.find((r) => r.id === roleId) ?? null;
const gereDesShifts = (u) => roleDe(u.role_id)?.gere_shifts === true;
const nomRole = (roleId) => roleDe(roleId)?.nom ?? '—';

const varianteRole = (slug) => ({
    super_admin: 'danger',
    administrateur: 'info',
    coordonnateur_equipe: 'warning',
    secretaire: 'success',
    autres: 'neutral',
}[slug] ?? 'neutral');

// ---- Recherche, filtres et tri : tout passe par une visite Inertia ----
// (liste paginée côté serveur ; pagination via withQueryString).
const recherche = ref(props.filtreRecherche ?? '');
const roleFiltre = ref(props.filtreRole ?? '');
const statutFiltre = ref(props.filtreStatut ?? '');
const accesFiltre = ref(props.filtreAcces ?? '');
const chargement = ref(false);

const visiter = (tri = props.tri) => {
    router.get(route('settings.users.index'), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
        ...(roleFiltre.value ? { role: roleFiltre.value } : {}),
        ...(statutFiltre.value ? { statut: statutFiltre.value } : {}),
        ...(accesFiltre.value ? { acces: accesFiltre.value } : {}),
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

const changerFiltre = () => {
    clearTimeout(rechercheTimeout);
    visiter();
};

const filtreApplique = computed(() => Boolean(props.filtreRecherche || props.filtreRole || props.filtreStatut || props.filtreAcces));

const libelleResultats = computed(() => {
    const total = props.users.total ?? 0;
    return `${total} compte${total > 1 ? 's' : ''}${filtreApplique.value ? ` trouvé${total > 1 ? 's' : ''}` : ''}`;
});

const messageAucunResultat = computed(() => (props.filtreRecherche
    ? `Aucun compte ne correspond à « ${props.filtreRecherche} ».`
    : 'Aucun compte ne correspond à ces filtres.'));

// ---- Colonnes (priorité : 1 = toujours visible, plus grand = masqué en premier) ----
const colonnes = [
    // Noms et prénoms passent à la ligne (jamais coupés) ; l'e-mail est tronqué avec infobulle.
    { cle: 'nom', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 160, tronquer: false },
    { cle: 'prenom', libelle: 'Prénom', triable: true, priorite: 1, largeurMin: 130, tronquer: false },
    { cle: 'email', libelle: 'E-mail', triable: true, priorite: 3, largeurMin: 210 },
    { cle: 'role', libelle: 'Rôle', triable: true, priorite: 2, largeurMin: 140, tronquer: false, valeur: (u) => (u.role_id ? nomRole(u.role_id) : null) },
    { cle: 'statut', libelle: 'Statut', triable: true, priorite: 4, largeurMin: 130, tronquer: false },
    { cle: 'acces', libelle: 'Accès', triable: true, priorite: 4, largeurMin: 120, valeur: (u) => (u.acces_suspendu ? 'Accès suspendu' : 'Autorisé') },
    { cle: 'shifts', libelle: 'Shifts gérés', triable: true, priorite: 5, largeurMin: 150, tronquer: false },
    { cle: 'servant', libelle: 'Lié à un servant(e)', triable: true, priorite: 3, largeurMin: 180, valeur: (u) => u.servant_nom },
];

// ---- Création (fenêtre modale) ----
const creationOuverte = ref(false);

const form = useForm({
    nom: '',
    prenom: '',
    email: '',
    password: '',
    role_id: '',
    telephone: '',
    statut: 'actif',
    acces_suspendu: false,
});

const ouvrirCreation = () => {
    form.clearErrors();
    creationOuverte.value = true;
};

const creer = async () => {
    if (form.acces_suspendu && !(await confirmer(
        `Créer le compte avec l'accès suspendu ? ${form.prenom} ${form.nom} ne pourra pas se connecter tant que l'accès n'est pas rétabli.`,
        { danger: true },
    ))) return;

    form.post(route('settings.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            creationOuverte.value = false;
        },
    });
};

// ---- Modification (fenêtre modale, plus d'édition en ligne) ----
const idEnEdition = ref(null);
// Relu dans les props à chaque réponse : les ajouts/retraits de shifts s'y reflètent.
const enEdition = computed(() => props.users.data.find((u) => u.id === idEnEdition.value) ?? null);

const edition = useForm({
    nom: '',
    prenom: '',
    role_id: '',
    statut: 'actif',
    acces_suspendu: false,
    telephone: '',
});

const editer = (u) => {
    edition.defaults({
        nom: u.nom ?? '',
        prenom: u.prenom ?? '',
        role_id: u.role_id ?? '',
        statut: u.statut,
        acces_suspendu: Boolean(u.acces_suspendu),
        telephone: u.telephone ?? '',
    });
    edition.reset();
    edition.clearErrors();
    shiftAAjouter.value = '';
    idEnEdition.value = u.id;
};

const fermerEdition = () => (idEnEdition.value = null);

const enregistrer = async () => {
    const u = enEdition.value;
    if (!u) return;

    // Confirmation seulement quand on bascule le blocage d'accès.
    if (edition.acces_suspendu !== Boolean(u.acces_suspendu)) {
        const message = edition.acces_suspendu
            ? `Suspendre l'accès au compte de ${u.name} ? Il sera déconnecté et ne pourra plus se connecter.`
            : `Rétablir l'accès au compte de ${u.name} ?`;
        if (!(await confirmer(message, { danger: edition.acces_suspendu }))) return;
    }

    edition.put(route('settings.users.update', u.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

// Shifts gérés (coordonnateurs) : gérés depuis la fenêtre de modification.
const shiftAAjouter = ref('');

const ajouterShift = () => {
    const u = enEdition.value;
    if (!u || !shiftAAjouter.value) return;

    router.post(route('shifts.members.store', shiftAAjouter.value), { user_id: u.id }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => (shiftAAjouter.value = ''),
    });
};

const retirerShift = async (s) => {
    if (!(await confirmer(`Retirer le shift ${s.shift_nom} de sa liste de gestion ?`, { danger: true }))) return;
    router.delete(route('shifts.members.destroy', [s.shift_id, s.affectation_id]), { preserveScroll: true, preserveState: true });
};

// ---- Actions regroupées ----
const actionsDe = (u, { avecModifier }) => [
    ...(avecModifier ? [{ cle: 'modifier', libelle: 'Modifier', icone: Pencil }] : []),
    {
        cle: 'delier',
        libelle: 'Délier du servant(e)',
        icone: Link2Off,
        desactive: !u.servant_id,
        aide: u.servant_id ? undefined : 'Ce compte n\'est lié à aucun servant(e).',
    },
    {
        cle: 'supprimer',
        libelle: 'Supprimer le compte',
        icone: Trash2,
        danger: true,
        desactive: u.id === moi.value,
        aide: u.id === moi.value ? 'Vous ne pouvez pas supprimer votre propre compte.' : undefined,
    },
];

const delier = async (u) => {
    if (!(await confirmer(
        `Délier le compte de ${u.name} de la fiche servant(e) « ${u.servant_nom} » ? Le compte de connexion et la fiche du servant(e) sont conservés : seul le lien entre les deux est supprimé.`,
        { title: 'Délier du servant(e)', danger: true },
    ))) return;
    router.delete(route('settings.users.servant.unlink', u.id), { preserveScroll: true, preserveState: true });
};

const supprimer = async (u) => {
    const message = u.servant_id
        ? `Supprimer le compte de ${u.name} ? Ce compte est lié à la fiche servant(e) « ${u.servant_nom} » : la fiche du servant(e) est conservée, seul le compte de connexion est supprimé.`
        : `Supprimer le compte de ${u.name} ?`;
    if (!(await confirmer(message, { title: 'Supprimer le compte', danger: true }))) return;
    router.delete(route('settings.users.destroy', u.id), { preserveScroll: true, preserveState: true });
};

const agir = (u, cle) => ({ modifier: editer, delier, supprimer }[cle]?.(u));

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100';
const classeFiltre = 'min-h-[44px] w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 sm:w-auto';
const classeCase = 'h-5 w-5 rounded border-neutral-300 text-danger shadow-sm focus:ring-danger dark:border-neutral-600 dark:bg-neutral-900';
</script>

<template>
    <Head title="Utilisateurs" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Utilisateurs' }]">
        <template #header>
            <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Paramètres — Utilisateurs">
                Paramètres — Utilisateurs
            </h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <SearchInput
                    :model-value="recherche"
                    placeholder="Rechercher un nom ou un e-mail…"
                    label="Rechercher un compte par nom ou e-mail"
                    @update:model-value="rechercher"
                />
                <select v-model="roleFiltre" aria-label="Filtrer par rôle" :class="classeFiltre" @change="changerFiltre">
                    <option value="">Tous les rôles</option>
                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                </select>
                <select v-model="statutFiltre" aria-label="Filtrer par statut" :class="classeFiltre" @change="changerFiltre">
                    <option value="">Tous les statuts</option>
                    <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="accesFiltre" aria-label="Filtrer par accès au compte" :class="classeFiltre" @change="changerFiltre">
                    <option value="">Tous les accès</option>
                    <option value="autorise">Accès autorisé</option>
                    <option value="suspendu">Accès suspendu</option>
                </select>
                <PrimaryButton type="button" class="min-h-[44px] justify-center sm:ml-auto" @click="ouvrirCreation">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nouveau compte
                </PrimaryButton>
            </div>

            <DataTable
                :colonnes="colonnes"
                :lignes="users.data"
                legende="Comptes utilisateurs de l'organisation"
                mode-tri="serveur"
                :tri="tri"
                :chargement="chargement"
                :filtre-actif="filtreApplique"
                :compteur="libelleResultats"
                message-vide="Aucun compte enregistré."
                :message-aucun-resultat="messageAucunResultat"
                @update:tri="visiter"
            >
                <template #cellule-email="{ ligne }">
                    <span :title="ligne.email">{{ ligne.email }}</span>
                </template>
                <template #cellule-role="{ ligne }">
                    <!-- Libellé du rôle jamais tronqué : il passe à la ligne si besoin. -->
                    <Badge v-if="ligne.role_id" :variant="varianteRole(ligne.role_slug)" class="max-w-full">
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ nomRole(ligne.role_id) }}</span>
                    </Badge>
                    <span v-else class="text-neutral-400">—</span>
                </template>
                <template #cellule-statut="{ ligne }">
                    <span class="flex flex-wrap gap-1">
                        <StatusBadge :statut="ligne.statut" domain="utilisateur" />
                        <Badge v-if="ligne.must_change_password" variant="warning">Doit changer son mot de passe</Badge>
                    </span>
                </template>
                <template #cellule-acces="{ ligne }">
                    <Badge v-if="ligne.acces_suspendu" variant="danger">Accès suspendu</Badge>
                    <Badge v-else variant="success">Autorisé</Badge>
                </template>
                <template #cellule-shifts="{ ligne }">
                    <span v-if="gereDesShifts(ligne) && ligne.shifts_geres.length" class="flex flex-wrap gap-1">
                        <Badge v-for="s in ligne.shifts_geres" :key="s.affectation_id" variant="neutral" class="max-w-full">
                            <span class="truncate" :title="s.shift_nom">{{ s.shift_nom }}</span>
                        </Badge>
                    </span>
                    <span v-else-if="gereDesShifts(ligne)" class="text-neutral-500 dark:text-neutral-400">Aucun</span>
                    <span v-else class="text-neutral-400">—</span>
                </template>
                <template #cellule-servant="{ ligne }">
                    <Link
                        v-if="ligne.servant_id"
                        :href="route('servants.show', ligne.servant_id)"
                        :title="ligne.servant_nom"
                        class="rounded font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >{{ ligne.servant_nom }}</Link>
                    <span v-else class="text-neutral-400">—</span>
                </template>

                <template #actions="{ ligne, mode }">
                    <button
                        v-if="mode === 'tableau'"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                        :aria-label="`Modifier le compte de ${ligne.name}`"
                        :title="`Modifier le compte de ${ligne.name}`"
                        @click="editer(ligne)"
                    >
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier le compte de ${ligne.name}`" @click="editer(ligne)">
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                        Modifier
                    </SecondaryButton>
                    <ActionsMenu
                        :libelle="`Autres actions pour le compte de ${ligne.name}`"
                        :actions="actionsDe(ligne, { avecModifier: false })"
                        @choisir="(cle) => agir(ligne, cle)"
                    />
                </template>
            </DataTable>

            <Pagination :links="users.links ?? []" label="Pagination des comptes" />
        </div>

        <!-- ===== Création d'un compte ===== -->
        <Modal :show="creationOuverte" max-width="2xl" labelledby="titre-creation-compte" @close="creationOuverte = false">
            <form class="p-6" @submit.prevent="creer">
                <h2 id="titre-creation-compte" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Nouveau compte</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="creation-prenom" value="Prénom" />
                        <TextInput id="creation-prenom" v-model="form.prenom" type="text" :class="classeChamp" required autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.prenom" />
                    </div>
                    <div>
                        <InputLabel for="creation-nom" value="Nom" />
                        <TextInput id="creation-nom" v-model="form.nom" type="text" :class="classeChamp" required autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.nom" />
                    </div>
                    <div>
                        <InputLabel for="creation-email" value="E-mail" />
                        <TextInput id="creation-email" v-model="form.email" type="email" :class="classeChamp" required autocomplete="off" />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>
                    <div>
                        <InputLabel for="creation-password" value="Mot de passe temporaire" />
                        <TextInput id="creation-password" v-model="form.password" type="text" :class="classeChamp" required autocomplete="new-password" />
                        <InputError class="mt-2" :message="form.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="creation-role" value="Rôle" />
                        <select id="creation-role" v-model="form.role_id" :class="classeChamp" required>
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.role_id" />
                    </div>
                    <div>
                        <InputLabel for="creation-telephone" value="Téléphone (optionnel)" />
                        <TextInput id="creation-telephone" v-model="form.telephone" type="tel" :class="classeChamp" />
                        <InputError class="mt-2" :message="form.errors.telephone" />
                    </div>
                    <div>
                        <InputLabel for="creation-statut" value="Statut" />
                        <select id="creation-statut" v-model="form.statut" :class="classeChamp">
                            <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.statut" />
                    </div>
                    <div class="flex items-start gap-3 sm:pt-6">
                        <input id="creation-acces" v-model="form.acces_suspendu" type="checkbox" :class="['mt-0.5', classeCase]" aria-describedby="creation-acces-aide" />
                        <div>
                            <label for="creation-acces" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Accès au compte suspendu</label>
                            <p id="creation-acces-aide" class="text-xs text-neutral-600 dark:text-neutral-400">La personne ne pourra pas se connecter tant que l'accès n'est pas rétabli.</p>
                            <InputError class="mt-2" :message="form.errors.acces_suspendu" />
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="creationOuverte = false">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Créer le compte</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- ===== Modification d'un compte ===== -->
        <Modal :show="enEdition !== null" max-width="2xl" labelledby="titre-edition-compte" @close="fermerEdition">
            <form v-if="enEdition" class="p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-compte" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier le compte de {{ enEdition.name }}
                </h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="edition-prenom" value="Prénom" />
                        <TextInput id="edition-prenom" v-model="edition.prenom" type="text" :class="classeChamp" required autocomplete="off" />
                        <InputError class="mt-2" :message="edition.errors.prenom" />
                    </div>
                    <div>
                        <InputLabel for="edition-nom" value="Nom" />
                        <TextInput id="edition-nom" v-model="edition.nom" type="text" :class="classeChamp" required autocomplete="off" />
                        <InputError class="mt-2" :message="edition.errors.nom" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="edition-email" value="E-mail" />
                        <input
                            id="edition-email"
                            :value="enEdition.email"
                            type="email"
                            readonly
                            aria-describedby="edition-email-aide"
                            :class="[classeChamp, 'bg-neutral-50 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-400']"
                        />
                        <p id="edition-email-aide" class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                            L'adresse e-mail sert d'identifiant de connexion et ne se modifie pas ici : la personne peut la changer depuis « Mon profil ».
                        </p>
                    </div>
                    <div>
                        <InputLabel for="edition-role" value="Rôle" />
                        <select id="edition-role" v-model="edition.role_id" :class="classeChamp" required>
                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                        </select>
                        <InputError class="mt-2" :message="edition.errors.role_id" />
                    </div>
                    <div>
                        <InputLabel for="edition-statut" value="Statut" />
                        <select id="edition-statut" v-model="edition.statut" :class="classeChamp">
                            <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError class="mt-2" :message="edition.errors.statut" />
                    </div>
                    <div>
                        <InputLabel for="edition-telephone" value="Téléphone (optionnel)" />
                        <TextInput id="edition-telephone" v-model="edition.telephone" type="tel" :class="classeChamp" />
                        <InputError class="mt-2" :message="edition.errors.telephone" />
                    </div>
                    <div class="flex items-start gap-3 sm:pt-6">
                        <input
                            id="edition-acces"
                            v-model="edition.acces_suspendu"
                            type="checkbox"
                            :class="['mt-0.5', classeCase]"
                            :disabled="enEdition.id === moi && !enEdition.acces_suspendu"
                            aria-describedby="edition-acces-aide"
                        />
                        <div>
                            <label for="edition-acces" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Accès au compte suspendu</label>
                            <p id="edition-acces-aide" class="text-xs text-neutral-600 dark:text-neutral-400">
                                <template v-if="enEdition.id === moi && !enEdition.acces_suspendu">Vous ne pouvez pas suspendre votre propre accès.</template>
                                <template v-else>La personne est déconnectée et ne peut plus se connecter tant que l'accès n'est pas rétabli.</template>
                            </p>
                            <InputError class="mt-2" :message="edition.errors.acces_suspendu" />
                        </div>
                    </div>
                </div>

                <!-- Shifts gérés : pris en compte immédiatement (indépendamment du bouton Enregistrer). -->
                <fieldset v-if="gereDesShifts(enEdition)" class="mt-6 rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                    <legend class="px-1 text-sm font-medium text-neutral-800 dark:text-neutral-200">Shifts gérés</legend>
                    <p class="text-xs text-neutral-600 dark:text-neutral-400">Les ajouts et retraits sont enregistrés immédiatement.</p>
                    <ul v-if="enEdition.shifts_geres.length" role="list" class="mt-3 flex flex-wrap gap-2">
                        <li v-for="s in enEdition.shifts_geres" :key="s.affectation_id" class="inline-flex max-w-full items-center gap-1 rounded-full bg-neutral-100 py-0.5 pl-3 pr-0.5 text-sm text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">
                            <span class="truncate" :title="s.shift_nom">{{ s.shift_nom }}</span>
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-neutral-500 hover:text-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-danger dark:text-neutral-400 dark:hover:text-danger-300"
                                :aria-label="`Retirer le shift ${s.shift_nom}`"
                                @click="retirerShift(s)"
                            >
                                <X class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">Aucun shift géré.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <SearchableSelect v-model="shiftAAjouter" :options="optionsShifts" placeholder="Choisir un shift…" class="min-h-[44px] w-full sm:w-72" aria-label="Shift à ajouter" />
                        <SecondaryButton class="min-h-[44px] justify-center" :disabled="!shiftAAjouter" @click="ajouterShift">Ajouter le shift</SecondaryButton>
                    </div>
                </fieldset>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="edition.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
