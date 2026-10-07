<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { UserCheck, GraduationCap, GripVertical, RotateCcw, UserPlus, UserX } from '@lucide/vue';

const props = defineProps({
    // Paginateur Laravel (30 par page) : { data, links, total, ... }
    servants: Object,
    compteurs: Object,
    nouveaux: { type: Boolean, default: false },
    // Tri serveur courant ({ cle, sens }), validé par liste blanche côté serveur.
    tri: { type: Object, default: () => ({ cle: null, sens: 'asc' }) },
    filtreRecherche: { type: String, default: '' },
    filtreStatut: { type: String, default: null },
    filtrePieu: { type: Number, default: null },
    // Pieux de l'organisation ({ id, nom }), fournis séparément de la page.
    pieux: { type: Array, default: () => [] },
});

const page = usePage();

// Rôle « Autres » : consultation seule, pas de création.
const lectureSeule = computed(() => Boolean(page.props.auth.lectureSeule));

const titre = computed(() => (props.nouveaux ? 'Servant(e)s recommandé(e)s' : 'Gestion des Servant(e)s'));

const statutsDisponibles = [
    { value: 'en_formation', label: 'Nouveau' },
    { value: 'actif', label: 'Ancien' },
    { value: 'suspendu', label: 'Relevé' },
    { value: 'retire', label: 'Permutant' },
];

// ---- Recherche, filtres et tri : tout passe par une visite Inertia ----
const recherche = ref(props.filtreRecherche ?? '');
const statutFiltre = ref(props.filtreStatut ?? '');
const pieuFiltre = ref(props.filtrePieu ?? '');
const chargement = ref(false);

const routeListe = () => (props.nouveaux ? route('servants.nouveaux') : route('servants.index'));

const visiter = (tri = props.tri) => {
    router.get(routeListe(), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
        ...(statutFiltre.value && !props.nouveaux ? { statut: statutFiltre.value } : {}),
        ...(pieuFiltre.value ? { pieu: pieuFiltre.value } : {}),
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

onBeforeUnmount(() => clearTimeout(rechercheTimeout));

const filtreActif = computed(() => Boolean(props.filtreRecherche || props.filtreStatut || props.filtrePieu));

const compteur = computed(() => {
    const n = props.servants.total ?? 0;
    return `${n} servant(e)${n > 1 ? 's' : ''}${filtreActif.value ? ` trouvé(e)${n > 1 ? 's' : ''}` : ''}`;
});

// ---- Ordre des colonnes (Conseil du Temple uniquement) ----
// Même liste blanche que User::COLONNES_SERVANTS côté serveur.
// Définition des colonnes du DataTable (tri côté serveur, liste blanche TriServeur).
const COLONNES = {
    nom: { libelle: 'Nom', triable: true, principale: true, priorite: 1, tronquer: false },
    prenom: { libelle: 'Prénom', triable: true, priorite: 1, carte: false, tronquer: false },
    statut: { libelle: 'Statut', triable: true, priorite: 2, tronquer: false },
    voir: { libelle: 'Voir', priorite: 1, libelleMasque: true, largeur: '7rem', largeurMin: 90, carte: false },
    pieu: { libelle: 'Pieu', triable: true, priorite: 2 },
};
const ORDRE_PAR_DEFAUT = Object.keys(COLONNES);

const estPermutationValide = (ordre) => Array.isArray(ordre)
    && ordre.length === ORDRE_PAR_DEFAUT.length
    && new Set(ordre).size === ordre.length
    && ordre.every((cle) => cle in COLONNES);

const peutReordonner = computed(() => ['administrateur', 'super_admin'].includes(page.props.auth.role));

const ordreServeur = () => {
    const ordre = page.props.preferences?.colonnesServants;
    return peutReordonner.value && estPermutationValide(ordre) ? [...ordre] : [...ORDRE_PAR_DEFAUT];
};

// Copie locale pour un retour visuel immédiat ; resynchronisée à chaque
// réponse du serveur (props partagées).
const ordreColonnes = ref(ordreServeur());
watch(() => page.props.preferences?.colonnesServants, () => (ordreColonnes.value = ordreServeur()));

const colonnes = computed(() => ordreColonnes.value.map((cle) => ({ cle, ...COLONNES[cle] })));

const estOrdreParDefaut = computed(() => ordreColonnes.value.every((cle, i) => cle === ORDRE_PAR_DEFAUT[i]));

const annonce = ref('');

const enregistrerOrdre = (ordre) => {
    router.patch(route('preferences.colonnes-servants.update'), { colonnes: ordre }, {
        preserveScroll: true,
        preserveState: true,
    });
};

const deplacerColonne = (cle, versIndex, { focus = false } = {}) => {
    const ordre = [...ordreColonnes.value];
    const deIndex = ordre.indexOf(cle);
    if (deIndex === -1 || versIndex < 0 || versIndex >= ordre.length || versIndex === deIndex) {
        return;
    }
    ordre.splice(deIndex, 1);
    ordre.splice(versIndex, 0, cle);
    ordreColonnes.value = ordre;
    annonce.value = `Colonne ${COLONNES[cle].libelle} déplacée en position ${versIndex + 1} sur ${ordre.length}.`;
    enregistrerOrdre(ordre);

    if (focus) {
        // Le nœud est déplacé dans le DOM : on redonne le focus à sa poignée.
        nextTick(() => document.querySelector(`[data-poignee-colonne="${cle}"]`)?.focus());
    }
};

const reinitialiserOrdre = () => {
    ordreColonnes.value = [...ORDRE_PAR_DEFAUT];
    annonce.value = 'Ordre des colonnes réinitialisé : Nom, Prénom, Statut, Voir, Pieu.';
    enregistrerOrdre(null);
};

// Accessibilité clavier : flèches gauche/droite, Début/Fin sur la poignée.
const onPoigneeKeydown = (event, cle) => {
    const index = ordreColonnes.value.indexOf(cle);
    const cibles = { ArrowLeft: index - 1, ArrowRight: index + 1, Home: 0, End: ordreColonnes.value.length - 1 };
    if (!(event.key in cibles)) {
        return;
    }
    event.preventDefault();
    deplacerColonne(cle, cibles[event.key], { focus: true });
};

// Glisser-déposer natif HTML5 (même technique que ShiftTemplates/Show.vue).
const colonneGlissee = ref(null);
const colonneSurvolee = ref(null);

const onDragStart = (event, cle) => {
    colonneGlissee.value = cle;
    event.dataTransfer.effectAllowed = 'move';
    // Firefox n'initie le glisser qu'avec une donnée attachée.
    event.dataTransfer.setData('text/plain', cle);
};

const onDrop = (cle) => {
    const source = colonneGlissee.value;
    colonneGlissee.value = null;
    colonneSurvolee.value = null;
    if (source && source !== cle) {
        deplacerColonne(source, ordreColonnes.value.indexOf(cle));
    }
};

const onDragEnd = () => {
    colonneGlissee.value = null;
    colonneSurvolee.value = null;
};

// Attributs de glisser-déposer d'un en-tête (vides pour les autres rôles).
const attributsEntete = (cle) => (peutReordonner.value
    ? {
        draggable: 'true',
        onDragstart: (e) => onDragStart(e, cle),
        onDragover: (e) => { e.preventDefault(); colonneSurvolee.value = cle; },
        onDragleave: () => { if (colonneSurvolee.value === cle) colonneSurvolee.value = null; },
        onDrop: (e) => { e.preventDefault(); onDrop(cle); },
        onDragend: onDragEnd,
    }
    : {});

const classeFiltre = 'min-h-[44px] w-full rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 sm:w-auto';

const attributsEnteteColonne = (colonne) => attributsEntete(colonne.cle);

const classesEntete = ({ cle }) => [
    colonneGlissee.value === cle ? 'opacity-50' : '',
    colonneSurvolee.value === cle && colonneGlissee.value && colonneGlissee.value !== cle ? 'bg-primary-50 dark:bg-primary-900/30' : '',
];
</script>

<template>
    <Head :title="titre" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: nouveaux ? 'Recommandés' : 'Servant(e)s' }]">
        <template #header>
            <div class="min-w-0">
                <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" :title="titre">
                    {{ titre }}
                </h2>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <p v-if="nouveaux" class="text-sm text-neutral-600 dark:text-neutral-400">
                Cette vue liste les servant(e)s au statut « Recommandé », en attente d'intégration.
            </p>
            <div v-if="!nouveaux" class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Anciens" :value="compteurs.actifs" :icon="UserCheck" tone="primary" />
                <StatCard label="Nouveaux" :value="compteurs.en_formation" :icon="GraduationCap" tone="primary" />
                <StatCard label="Recommandés" :value="compteurs.recommandes" :icon="UserPlus" tone="primary" />
                <StatCard label="Relevés" :value="compteurs.suspendus" :icon="UserX" tone="primary" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                <SearchInput :model-value="recherche" @update:model-value="rechercher" placeholder="Rechercher un nom, un prénom…" label="Rechercher un servant(e) par nom ou prénom" />
                <select v-if="!nouveaux" v-model="statutFiltre" @change="changerFiltre" aria-label="Filtrer par statut" :class="classeFiltre">
                    <option value="">Tous les statuts</option>
                    <option v-for="s in statutsDisponibles" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="pieuFiltre" @change="changerFiltre" aria-label="Filtrer par pieu" :class="classeFiltre">
                    <option value="">Tous les pieux</option>
                    <option v-for="p in pieux" :key="p.id" :value="p.id">{{ p.nom }}</option>
                </select>
                <Link
                    v-if="!lectureSeule"
                    :href="route('servants.create')"
                    class="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary sm:ml-auto px-4 text-sm font-medium text-white transition hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
                >
                    <span aria-hidden="true">+</span>
                    Ajouter un Servant(e)
                </Link>
            </div>

            <!-- Réordonnancement des colonnes : uniquement en mode tableau (grand écran). -->
            <div v-if="peutReordonner" class="hidden flex-wrap items-center justify-between gap-2 text-xs text-neutral-600 dark:text-neutral-400 lg:flex">
                <p id="aide-colonnes-servants">
                    Réordonnez les colonnes en glissant-déposant les en-têtes, ou au clavier avec les flèches gauche/droite sur la poignée.
                </p>
                <button
                    type="button"
                    class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg px-3 font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-50 dark:text-neutral-200 dark:ring-neutral-700 dark:hover:bg-neutral-700"
                    :disabled="estOrdreParDefaut"
                    @click="reinitialiserOrdre"
                >
                    <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" />
                    Réinitialiser l’ordre
                </button>
            </div>
            <div v-if="peutReordonner" class="sr-only" aria-live="polite" aria-atomic="true">{{ annonce }}</div>

            <DataTable
                :colonnes="colonnes"
                :lignes="servants.data"
                :legende="titre"
                mode-tri="serveur"
                :tri="tri"
                :chargement="chargement"
                @update:tri="visiter"
                :filtre-actif="filtreActif"
                :compteur="compteur"
                :message-vide="nouveaux ? 'Aucun servant(e) recommandé(e).' : 'Aucun servant(e) pour le moment.'"
                message-aucun-resultat="Aucun servant(e) ne correspond à ces critères."
                :attributs-entete="attributsEnteteColonne"
                :classes-entete="classesEntete"
            >
                <!-- Poignées de déplacement des colonnes (Conseil du Temple, mode tableau). -->
                <template v-for="cle in ORDRE_PAR_DEFAUT" :key="cle" #[`entete-${cle}`]>
                    <button
                        v-if="peutReordonner"
                        type="button"
                        :data-poignee-colonne="cle"
                        class="mr-1 inline-flex h-11 w-7 cursor-grab items-center justify-center rounded align-middle text-neutral-400 hover:text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:hover:text-neutral-200"
                        :aria-label="`Déplacer la colonne ${COLONNES[cle].libelle} (position ${ordreColonnes.indexOf(cle) + 1} sur ${ordreColonnes.length})`"
                        aria-describedby="aide-colonnes-servants"
                        @keydown="onPoigneeKeydown($event, cle)"
                    >
                        <GripVertical class="h-3.5 w-3.5" aria-hidden="true" />
                    </button>
                </template>

                <template #cellule-nom="{ ligne, mode }">
                    <Link
                        v-if="mode === 'carte'"
                        :href="route('servants.show', ligne.id)"
                        class="rounded text-primary hover:text-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-primary-300"
                    >{{ ligne.nom }} {{ ligne.prenom }}</Link>
                    <template v-else>{{ ligne.nom }}</template>
                </template>
                <template #cellule-statut="{ ligne }">
                    <StatusBadge :statut="ligne.statut" domain="servant" />
                </template>
                <template #cellule-voir="{ ligne }">
                    <Link
                        :href="route('servants.show', ligne.id)"
                        class="-my-3 inline-flex min-h-[44px] items-center rounded font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >
                        Voir<span class="sr-only"> {{ ligne.prenom }} {{ ligne.nom }}</span>
                    </Link>
                </template>
            </DataTable>

            <Pagination :links="servants.links ?? []" label="Pagination des servant(e)s" />
        </div>
    </AuthenticatedLayout>
</template>
