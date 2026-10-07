<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SortableHeader from '@/Components/SortableHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useTableSort } from '@/composables/useTableSort';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { UserCheck, GraduationCap, GripVertical, RotateCcw, UserPlus, UserX } from '@lucide/vue';

const props = defineProps({
    servants: Array,
    compteurs: Object,
    nouveaux: { type: Boolean, default: false },
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

const pieuxDisponibles = computed(() => [...new Set(props.servants.map((s) => s.pieu).filter(Boolean))].sort());

const { recherche, resultats: servantsCherches } = useTableSearch(() => props.servants, ['nom', 'prenom']);

const statutFiltre = ref('');
const pieuFiltre = ref('');
const servantsFiltresParColonne = computed(() => servantsCherches.value
    .filter((s) => !statutFiltre.value || s.statut === statutFiltre.value)
    .filter((s) => !pieuFiltre.value || s.pieu === pieuFiltre.value));

const { sortKey, sortDirection, toggleSort, sorted: servantsFiltres } = useTableSort(() => servantsFiltresParColonne.value);

// Sur petit écran, la colonne Nom reste collée à gauche pendant le défilement
// horizontal du tableau (fond opaque pour masquer les colonnes qui glissent dessous).
const colonneCollanteEntete = 'max-sm:sticky max-sm:left-0 max-sm:z-10 max-sm:bg-neutral-50 max-sm:dark:bg-neutral-900';
const colonneCollanteCellule = 'max-sm:sticky max-sm:left-0 max-sm:z-10 max-sm:bg-white max-sm:dark:bg-neutral-800';

// ---- Ordre des colonnes (Conseil du Temple uniquement) ----
// Même liste blanche que User::COLONNES_SERVANTS côté serveur.
const COLONNES = {
    nom: { libelle: 'Nom', tri: 'nom' },
    prenom: { libelle: 'Prénom', tri: 'prenom' },
    statut: { libelle: 'Statut', tri: 'statut' },
    voir: { libelle: 'Voir', tri: null },
    pieu: { libelle: 'Pieu', tri: 'pieu' },
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

const classesEntete = (cle, index) => [
    index === 0 ? colonneCollanteEntete : '',
    colonneGlissee.value === cle ? 'opacity-50' : '',
    colonneSurvolee.value === cle && colonneGlissee.value && colonneGlissee.value !== cle ? 'bg-primary-50 dark:bg-primary-900/30' : '',
];
</script>

<template>
    <Head :title="titre" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: nouveaux ? 'Recommandés' : 'Servant(e)s' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    {{ titre }}
                </h2>
                <Link v-if="!lectureSeule" :href="route('servants.create')">
                    <PrimaryButton>+ Ajouter un Servant(e)</PrimaryButton>
                </Link>
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

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput v-model="recherche" placeholder="Rechercher un nom, un prénom…" />
                <select v-if="!nouveaux" v-model="statutFiltre" class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les statuts</option>
                    <option v-for="s in statutsDisponibles" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="pieuFiltre" class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les pieux</option>
                    <option v-for="p in pieuxDisponibles" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>

            <div v-if="peutReordonner" class="flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-600 dark:text-neutral-400">
                <p id="aide-colonnes-servants">
                    Réordonnez les colonnes en glissant-déposant les en-têtes, ou au clavier avec les flèches gauche/droite sur la poignée.
                </p>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 font-medium text-neutral-700 ring-1 ring-neutral-200 transition hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-50 dark:text-neutral-200 dark:ring-neutral-700 dark:hover:bg-neutral-700"
                    :disabled="estOrdreParDefaut"
                    @click="reinitialiserOrdre"
                >
                    <RotateCcw class="h-3.5 w-3.5" aria-hidden="true" />
                    Réinitialiser l’ordre
                </button>
                <div class="sr-only" aria-live="polite" aria-atomic="true">{{ annonce }}</div>
            </div>

            <div class="overflow-hidden rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <caption class="sr-only">{{ titre }}</caption>
                        <thead class="bg-neutral-50 dark:bg-neutral-900">
                            <tr>
                                <!-- Ordre par défaut : Nom, Prénom, Statut, Voir, Pieu ; le Conseil du Temple peut le réordonner. -->
                                <template v-for="(cle, index) in ordreColonnes" :key="cle">
                                    <SortableHeader
                                        v-if="COLONNES[cle].tri"
                                        :label="COLONNES[cle].libelle"
                                        :sort-key="COLONNES[cle].tri"
                                        :active-key="sortKey"
                                        :direction="sortDirection"
                                        scope="col"
                                        :class="classesEntete(cle, index)"
                                        v-bind="attributsEntete(cle)"
                                        @sort="toggleSort"
                                    >
                                        <template v-if="peutReordonner" #avant>
                                            <button
                                                type="button"
                                                :data-poignee-colonne="cle"
                                                class="mr-1 inline-flex cursor-grab items-center rounded p-0.5 align-middle text-neutral-400 hover:text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:hover:text-neutral-200"
                                                :aria-label="`Déplacer la colonne ${COLONNES[cle].libelle} (position ${index + 1} sur ${ordreColonnes.length})`"
                                                aria-describedby="aide-colonnes-servants"
                                                @keydown="onPoigneeKeydown($event, cle)"
                                            >
                                                <GripVertical class="h-3.5 w-3.5" aria-hidden="true" />
                                            </button>
                                        </template>
                                    </SortableHeader>
                                    <th
                                        v-else
                                        scope="col"
                                        class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400"
                                        :class="classesEntete(cle, index)"
                                        v-bind="attributsEntete(cle)"
                                    >
                                        <button
                                            v-if="peutReordonner"
                                            type="button"
                                            :data-poignee-colonne="cle"
                                            class="mr-1 inline-flex cursor-grab items-center rounded p-0.5 align-middle text-neutral-400 hover:text-neutral-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:hover:text-neutral-200"
                                            :aria-label="`Déplacer la colonne ${COLONNES[cle].libelle} (position ${index + 1} sur ${ordreColonnes.length})`"
                                            aria-describedby="aide-colonnes-servants"
                                            @keydown="onPoigneeKeydown($event, cle)"
                                        >
                                            <GripVertical class="h-3.5 w-3.5" aria-hidden="true" />
                                        </button>
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700 bg-white dark:bg-neutral-800">
                            <tr v-if="servantsFiltres.length === 0">
                                <td :colspan="ordreColonnes.length" class="px-6 py-8 text-center text-neutral-600 dark:text-neutral-400">
                                    <template v-if="recherche || statutFiltre || pieuFiltre">Aucun servant(e) ne correspond à ces critères.</template>
                                    <template v-else-if="nouveaux">Aucun servant(e) recommandé(e).</template>
                                    <template v-else>Aucun servant(e) pour le moment.</template>
                                </td>
                            </tr>
                            <tr v-for="servant in servantsFiltres" :key="servant.id">
                                <template v-for="(cle, index) in ordreColonnes" :key="cle">
                                    <th v-if="cle === 'nom'" scope="row" class="whitespace-nowrap px-6 py-4 text-left text-sm font-medium text-neutral-900 dark:text-neutral-100" :class="index === 0 ? colonneCollanteCellule : ''">
                                        {{ servant.nom }}
                                    </th>
                                    <td v-else-if="cle === 'prenom'" class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100" :class="index === 0 ? colonneCollanteCellule : ''">
                                        {{ servant.prenom }}
                                    </td>
                                    <td v-else-if="cle === 'statut'" class="whitespace-nowrap px-6 py-4 text-sm" :class="index === 0 ? colonneCollanteCellule : ''">
                                        <StatusBadge :statut="servant.statut" domain="servant" />
                                    </td>
                                    <td v-else-if="cle === 'voir'" class="whitespace-nowrap px-6 py-4 text-sm" :class="index === 0 ? colonneCollanteCellule : ''">
                                        <Link :href="route('servants.show', servant.id)" class="font-medium text-primary-light hover:text-primary">
                                            Voir<span class="sr-only"> {{ servant.prenom }} {{ servant.nom }}</span>
                                        </Link>
                                    </td>
                                    <td v-else-if="cle === 'pieu'" class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400" :class="index === 0 ? colonneCollanteCellule : ''">
                                        {{ servant.pieu ?? '—' }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
