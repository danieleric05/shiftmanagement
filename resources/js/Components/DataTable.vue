<script setup>
import SortableHeader from '@/Components/SortableHeader.vue';
import { REQUETE_LG, useMediaQuery } from '@/composables/useMediaQuery';
import { useTableSort } from '@/composables/useTableSort';
import { ArrowDown, ArrowUp } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, useId, useSlots } from 'vue';

/**
 * Tableau partagé de l'application, sans aucun défilement horizontal.
 *
 * - À partir de `lg` : tableau classique en `table-layout: fixed` sur 100 %
 *   de la largeur ; les colonnes secondaires sont masquées par priorité selon
 *   la largeur disponible et leur contenu est regroupé en sous-texte dans la
 *   cellule principale (aucune information perdue).
 * - Sous `lg` (ou si le conteneur est trop étroit) : une carte par ligne
 *   (libellé / valeur, actions en bas) et un sélecteur « Trier par » + sens.
 * - Tri client (liste complète en mémoire) ou serveur (`modeTri="serveur"` :
 *   le composant émet `update:tri` et la page relance la visite Inertia).
 *
 * Définition d'une colonne :
 *   { cle, libelle, triable?, cleTri? (défaut : cle), valeurTri?(ligne) (tri client),
 *     valeur?(ligne), alignement?: 'debut'|'fin'|'centre', priorite?: 1 = toujours
 *     visible, plus grand = masqué en premier (défaut 2), principale?: bool,
 *     largeur?: largeur fixe en rem ou px ('7rem'), largeurMin?: px (masquage et répartition, défaut 110),
 *     tronquer?: bool (défaut true : une ligne + title), libelleMasque?: bool,
 *     carte?: false pour ne pas répéter la colonne dans la carte (ex. déjà dans le titre) }
 *
 * Slots : `cellule-<cle>` ({ ligne, valeur, colonne, mode }), `entete-<cle>`
 * ({ colonne, index }), `actions` ({ ligne, mode }), `vide`.
 */
const props = defineProps({
    colonnes: { type: Array, required: true },
    lignes: { type: Array, required: true },
    legende: { type: String, required: true },
    cleLigne: { type: [String, Function], default: 'id' },
    modeTri: { type: String, default: 'client', validator: (v) => ['client', 'serveur'].includes(v) },
    // { cle, sens } : tri courant (serveur) ou initial seulement (client : lu au montage).
    tri: { type: Object, default: null },
    chargement: { type: Boolean, default: false },
    filtreActif: { type: Boolean, default: false },
    messageVide: { type: String, default: 'Aucune donnée pour le moment.' },
    messageAucunResultat: { type: String, default: 'Aucun résultat ne correspond à ces critères.' },
    // Texte annoncé (aria-live) ; à défaut « N élément(s) ».
    compteur: { type: String, default: null },
    libelleActions: { type: String, default: 'Actions' },
    largeurActions: { type: String, default: '8.5rem' },
    // Hooks pour les en-têtes (ex. glisser-déposer des colonnes des servants).
    attributsEntete: { type: Function, default: null },
    classesEntete: { type: Function, default: null },
});

const emit = defineEmits(['update:tri']);
const slots = useSlots();
const id = useId();

const estLg = useMediaQuery(REQUETE_LG);
const racine = ref(null);
const largeur = ref(Infinity);
let observateur = null;

onMounted(() => {
    largeur.value = racine.value?.clientWidth ?? Infinity;
    if (typeof ResizeObserver !== 'undefined') {
        observateur = new ResizeObserver(([entree]) => (largeur.value = entree.contentRect.width));
        observateur.observe(racine.value);
    }
});
onBeforeUnmount(() => observateur?.disconnect());

const avecActions = computed(() => Boolean(slots.actions));

const colonnesNormalisees = computed(() => props.colonnes.map((c, index) => ({
    triable: false,
    priorite: 2,
    largeurMin: 110,
    tronquer: true,
    alignement: 'debut',
    ...c,
    cleTri: c.cleTri ?? c.cle,
    index,
})));

const colonnePrincipale = computed(() => colonnesNormalisees.value.find((c) => c.principale) ?? colonnesNormalisees.value[0]);

const remToPx = (valeur) => {
    const m = /^([\d.]+)(rem|px)$/.exec(valeur ?? '');
    if (!m) return 136;
    return m[2] === 'rem' ? parseFloat(m[1]) * 16 : parseFloat(m[1]);
};

// Masquage par priorité : tant que la somme des largeurs minimales dépasse la
// largeur disponible, on masque la colonne de plus grande priorité (la plus à
// droite en cas d'égalité). La colonne principale et les priorités 1 restent.
const colonnesMasquees = computed(() => {
    const masquees = new Set();
    if (!Number.isFinite(largeur.value)) return masquees;

    const disponible = largeur.value - (avecActions.value ? remToPx(props.largeurActions) : 0);
    let total = colonnesNormalisees.value.reduce((s, c) => s + c.largeurMin, 0);
    const candidates = colonnesNormalisees.value
        .filter((c) => c !== colonnePrincipale.value && c.priorite > 1)
        .sort((a, b) => b.priorite - a.priorite || b.index - a.index);

    for (const c of candidates) {
        if (total <= disponible) break;
        masquees.add(c.cle);
        total -= c.largeurMin;
    }

    return masquees;
});

const colonnesVisibles = computed(() => colonnesNormalisees.value.filter((c) => !colonnesMasquees.value.has(c.cle)));
const colonnesRegroupees = computed(() => colonnesNormalisees.value.filter((c) => colonnesMasquees.value.has(c.cle)));

// Cartes sous `lg`, ou si même les colonnes indispensables ne tiennent pas.
const modeCartes = computed(() => {
    if (!estLg.value) return true;
    if (!Number.isFinite(largeur.value)) return false;
    const indispensable = colonnesVisibles.value.reduce((s, c) => s + c.largeurMin, 0)
        + (avecActions.value ? remToPx(props.largeurActions) : 0);
    return indispensable > largeur.value;
});

// ---- Tri ----
const accesseurs = () => Object.fromEntries(colonnesNormalisees.value
    .filter((c) => c.triable)
    .map((c) => [c.cleTri, c.valeurTri ?? c.valeur ?? ((l) => l[c.cle])]));

const triClient = useTableSort(() => props.lignes, props.tri?.cle ?? null, props.tri?.sens ?? 'asc', accesseurs);

const cleActive = computed(() => (props.modeTri === 'serveur' ? props.tri?.cle ?? null : triClient.sortKey.value));
const sensActif = computed(() => (props.modeTri === 'serveur' ? props.tri?.sens ?? 'asc' : triClient.sortDirection.value));

const appliquerTri = (cle, sens) => {
    if (props.modeTri === 'client') triClient.setSort(cle, sens);
    emit('update:tri', { cle: cle || null, sens: cle ? sens : 'asc' });
};

const basculerTri = (cle) => appliquerTri(cle, cleActive.value === cle && sensActif.value === 'asc' ? 'desc' : 'asc');

const lignesAffichees = computed(() => (props.modeTri === 'client' ? triClient.sorted.value : props.lignes));
const colonnesTriables = computed(() => colonnesNormalisees.value.filter((c) => c.triable));

const libelleSens = computed(() => (sensActif.value === 'desc' ? 'Décroissant' : 'Croissant'));

// ---- Rendu ----
const cle = (ligne, index) => (typeof props.cleLigne === 'function' ? props.cleLigne(ligne) : ligne[props.cleLigne] ?? index);
const valeur = (ligne, colonne) => (colonne.valeur ? colonne.valeur(ligne) : ligne[colonne.cle]);
const texte = (v) => (v == null || v === '' ? '—' : String(v));

const classeAlignement = (c) => ({ fin: 'text-right', centre: 'text-center' }[c.alignement] ?? 'text-left');
const classeCellule = 'px-3 py-3 xl:px-4';

const texteCompteur = computed(() => {
    if (props.chargement) return 'Chargement…';
    if (props.compteur !== null) return props.compteur;
    const n = props.lignes.length;
    return `${n} élément${n > 1 ? 's' : ''}`;
});

const messageEtatVide = computed(() => {
    if (props.chargement) return 'Chargement…';
    return props.filtreActif ? props.messageAucunResultat : props.messageVide;
});

// Largeurs calculées en pixels à partir de la largeur mesurée du conteneur :
// les colonnes à largeur fixe (rem) et la colonne Actions d'abord, puis le
// reste réparti au prorata de `largeurMin` (la cellule principale reçoit un
// supplément quand elle accueille des colonnes regroupées). La somme est
// exactement la largeur disponible : aucun débordement possible.
const largeursColonnes = computed(() => {
    if (!Number.isFinite(largeur.value) || largeur.value <= 0) return {};
    const fixes = colonnesVisibles.value.filter((c) => c.largeur);
    const variables = colonnesVisibles.value.filter((c) => !c.largeur);
    const poids = (c) => c.largeurMin * (c === colonnePrincipale.value && colonnesRegroupees.value.length ? 1.6 : 1);
    const reste = largeur.value
        - fixes.reduce((s, c) => s + remToPx(c.largeur), 0)
        - (avecActions.value ? remToPx(props.largeurActions) : 0);
    const total = variables.reduce((s, c) => s + poids(c), 0) || 1;

    return Object.fromEntries([
        ...fixes.map((c) => [c.cle, `${remToPx(c.largeur)}px`]),
        ...variables.map((c) => [c.cle, `${Math.max(0, Math.floor((reste * poids(c)) / total))}px`]),
    ]);
});
const largeurColonne = (c) => largeursColonnes.value[c.cle] ?? null;

const attrsEntete = (c, i) => (props.attributsEntete ? props.attributsEntete(c, i) : {});
const clsEntete = (c, i) => (props.classesEntete ? props.classesEntete(c, i) : '');
</script>

<template>
    <div ref="racine" class="min-w-0 max-w-full" :aria-busy="chargement ? 'true' : 'false'">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
            <p class="text-sm text-neutral-600 dark:text-neutral-400" role="status" aria-live="polite" aria-atomic="true">
                {{ texteCompteur }}
            </p>

            <!-- Mode cartes : les en-têtes n'existent plus, le tri passe par ce sélecteur. -->
            <div v-if="modeCartes && colonnesTriables.length" class="flex flex-wrap items-center gap-2">
                <label :for="`${id}-tri`" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Trier par</label>
                <select
                    :id="`${id}-tri`"
                    :value="cleActive ?? ''"
                    class="min-h-[44px] max-w-[12rem] rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                    @change="appliquerTri($event.target.value, sensActif)"
                >
                    <option value="">Ordre par défaut</option>
                    <option v-for="c in colonnesTriables" :key="c.cleTri" :value="c.cleTri">{{ c.libelle }}</option>
                </select>
                <button
                    type="button"
                    class="inline-flex min-h-[44px] items-center gap-1.5 rounded-lg px-3 text-sm font-medium text-neutral-700 ring-1 ring-neutral-300 transition hover:bg-neutral-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-50 dark:text-neutral-200 dark:ring-neutral-600 dark:hover:bg-neutral-700"
                    :disabled="!cleActive"
                    :aria-label="`Sens du tri : ${libelleSens.toLowerCase()}. Activer pour inverser.`"
                    @click="appliquerTri(cleActive, sensActif === 'asc' ? 'desc' : 'asc')"
                >
                    <ArrowDown v-if="sensActif === 'desc'" class="h-4 w-4" aria-hidden="true" />
                    <ArrowUp v-else class="h-4 w-4" aria-hidden="true" />
                    {{ libelleSens }}
                </button>
            </div>
        </div>

        <!-- ===== Mode cartes ===== -->
        <template v-if="modeCartes">
            <div
                v-if="lignesAffichees.length === 0"
                class="rounded-xl bg-white px-4 py-8 text-center text-sm text-neutral-600 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700"
            >
                <slot name="vide" :chargement="chargement" :filtre-actif="filtreActif">{{ messageEtatVide }}</slot>
            </div>
            <ul v-else role="list" :aria-label="legende" class="space-y-3 transition-opacity" :class="chargement ? 'opacity-60' : ''">
                <li
                    v-for="(ligne, i) in lignesAffichees"
                    :key="cle(ligne, i)"
                    class="min-w-0 rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700"
                >
                    <div class="min-w-0 break-words text-base font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                        <slot :name="`cellule-${colonnePrincipale.cle}`" :ligne="ligne" :valeur="valeur(ligne, colonnePrincipale)" :colonne="colonnePrincipale" mode="carte">
                            {{ texte(valeur(ligne, colonnePrincipale)) }}
                        </slot>
                    </div>
                    <dl class="mt-3 grid grid-cols-1 gap-x-4 gap-y-3 sm:grid-cols-2">
                        <template v-for="c in colonnesNormalisees" :key="c.cle">
                            <div v-if="c !== colonnePrincipale && c.carte !== false" class="min-w-0">
                                <dt class="text-xs font-medium text-neutral-500 dark:text-neutral-400" :class="c.libelleMasque ? 'sr-only' : ''">{{ c.libelle }}</dt>
                                <dd class="mt-0.5 min-w-0 break-words text-sm text-neutral-800 [overflow-wrap:anywhere] dark:text-neutral-200">
                                    <slot :name="`cellule-${c.cle}`" :ligne="ligne" :valeur="valeur(ligne, c)" :colonne="c" mode="carte">
                                        {{ texte(valeur(ligne, c)) }}
                                    </slot>
                                </dd>
                            </div>
                        </template>
                    </dl>
                    <div v-if="avecActions" class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-neutral-100 pt-3 dark:border-neutral-700">
                        <slot name="actions" :ligne="ligne" mode="carte" />
                    </div>
                </li>
            </ul>
        </template>

        <!-- ===== Mode tableau (≥ lg) ===== -->
        <div v-else class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
            <table class="w-full table-fixed divide-y divide-neutral-100 transition-opacity dark:divide-neutral-700" :class="chargement ? 'opacity-60' : ''">
                <caption class="sr-only">{{ legende }}</caption>
                <colgroup>
                    <col v-for="c in colonnesVisibles" :key="c.cle" :style="largeurColonne(c) ? { width: largeurColonne(c) } : null" />
                    <col v-if="avecActions" :style="{ width: largeurActions }" />
                </colgroup>
                <thead class="bg-neutral-50 dark:bg-neutral-900">
                    <tr>
                        <template v-for="(c, index) in colonnesVisibles" :key="c.cle">
                            <SortableHeader
                                v-if="c.triable"
                                :label="c.libelle"
                                :sort-key="c.cleTri"
                                :active-key="cleActive"
                                :direction="sensActif"
                                :cell-class="classeCellule"
                                :align="c.alignement === 'fin' ? 'end' : c.alignement === 'centre' ? 'center' : 'start'"
                                :class="clsEntete(c, index)"
                                v-bind="attrsEntete(c, index)"
                                @sort="basculerTri"
                            >
                                <template v-if="slots[`entete-${c.cle}`]" #avant>
                                    <slot :name="`entete-${c.cle}`" :colonne="c" :index="index" />
                                </template>
                            </SortableHeader>
                            <th
                                v-else
                                scope="col"
                                class="text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400"
                                :class="[classeCellule, classeAlignement(c), clsEntete(c, index)]"
                                v-bind="attrsEntete(c, index)"
                            >
                                <slot :name="`entete-${c.cle}`" :colonne="c" :index="index" />
                                <span :class="c.libelleMasque ? 'sr-only' : 'break-words'">{{ c.libelle }}</span>
                            </th>
                        </template>
                        <th v-if="avecActions" scope="col" class="text-right text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400" :class="classeCellule">
                            {{ libelleActions }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100 bg-white dark:divide-neutral-700 dark:bg-neutral-800">
                    <tr v-if="lignesAffichees.length === 0">
                        <td :colspan="colonnesVisibles.length + (avecActions ? 1 : 0)" class="px-6 py-8 text-center text-sm text-neutral-600 dark:text-neutral-400">
                            <slot name="vide" :chargement="chargement" :filtre-actif="filtreActif">{{ messageEtatVide }}</slot>
                        </td>
                    </tr>
                    <tr
                        v-for="(ligne, i) in lignesAffichees"
                        :key="cle(ligne, i)"
                        class="align-top transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-700/40"
                    >
                        <template v-for="c in colonnesVisibles" :key="c.cle">
                            <component
                                :is="c === colonnePrincipale ? 'th' : 'td'"
                                :scope="c === colonnePrincipale ? 'row' : undefined"
                                class="min-w-0 text-sm"
                                :class="[classeCellule, classeAlignement(c), c === colonnePrincipale
                                    ? 'font-medium text-neutral-900 dark:text-neutral-100'
                                    : 'font-normal text-neutral-800 dark:text-neutral-200']"
                            >
                                <div class="min-w-0" :class="c.tronquer ? 'truncate' : 'break-words [overflow-wrap:anywhere]'" :title="c.tronquer ? texte(valeur(ligne, c)) : undefined">
                                    <slot :name="`cellule-${c.cle}`" :ligne="ligne" :valeur="valeur(ligne, c)" :colonne="c" mode="tableau">
                                        {{ texte(valeur(ligne, c)) }}
                                    </slot>
                                </div>
                                <!-- Colonnes masquées faute de place : regroupées en sous-texte. -->
                                <dl v-if="c === colonnePrincipale && colonnesRegroupees.length" class="mt-1 space-y-1 text-xs font-normal text-neutral-600 dark:text-neutral-400">
                                    <div v-for="r in colonnesRegroupees" :key="r.cle" class="flex min-w-0 flex-wrap items-baseline gap-x-1">
                                        <dt class="shrink-0" :class="r.libelleMasque ? 'sr-only' : ''">{{ r.libelle }} :</dt>
                                        <dd class="min-w-0 break-words [overflow-wrap:anywhere]">
                                            <slot :name="`cellule-${r.cle}`" :ligne="ligne" :valeur="valeur(ligne, r)" :colonne="r" mode="regroupe">
                                                {{ texte(valeur(ligne, r)) }}
                                            </slot>
                                        </dd>
                                    </div>
                                </dl>
                            </component>
                        </template>
                        <td v-if="avecActions" class="text-right text-sm" :class="classeCellule">
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <slot name="actions" :ligne="ligne" mode="tableau" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
