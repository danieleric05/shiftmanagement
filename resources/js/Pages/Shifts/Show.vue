<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import Badge from '@/Components/Badge.vue';
import DangerButton from '@/Components/DangerButton.vue';
import DataTable from '@/Components/DataTable.vue';
import EtapesAffectationModal from '@/Components/EtapesAffectationModal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, UserMinus, UserPlus } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

const props = defineProps({
    shift: Object,
    positions: Array,
    servantsDisponibles: Array,
    postesDisponibles: Array,
});

const { confirmer } = useConfirm();

// Rôle « Autres » : consultation du roster sans aucune action.
const lectureSeule = computed(() => Boolean(usePage().props.auth.lectureSeule));

// Filtre de recherche client sur le tableau des rôles/titulaires, pour
// naviguer facilement dans un roster de 20+ postes.
const recherche = ref('');
const positionsFiltrees = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    if (q === '') return props.positions;

    return props.positions.filter((p) => p.nom.toLowerCase().includes(q)
        || (p.titulaire?.nom_complet.toLowerCase().includes(q) ?? false));
});

const compteur = computed(() => {
    const n = positionsFiltrees.value.length;
    return `${n} rôle${n > 1 ? 's' : ''}${recherche.value.trim() ? ` trouvé${n > 1 ? 's' : ''}` : ''}`;
});

// ---- Étapes clés du parcours affichées sur le roster ----
const ETAPES = [
    { cle: 'protection_jeunesse', libelle: "Protection de l'enfance" },
    { cle: 'badge', libelle: 'Badge' },
    { cle: 'photo', libelle: 'Photo' },
];
const etapeDe = (p, cle) => p.titulaire?.etapes?.[cle] ?? null;

// ---- Colonnes (tri client : roster complet en mémoire ; ordre par défaut :
// postes occupés puis vacants, fourni par le serveur) ----
const colonnes = [
    { cle: 'nom', libelle: 'Rôle', triable: true, principale: true, priorite: 1, largeurMin: 160, tronquer: false },
    { cle: 'titulaire', libelle: 'Titulaire', triable: true, priorite: 1, largeurMin: 170, valeur: (p) => p.titulaire?.nom_complet ?? null },
    { cle: 'appel', libelle: 'Appel', triable: true, priorite: 3, largeurMin: 120, valeur: (p) => p.titulaire?.titre_leadership ?? null },
    ...ETAPES.map((e) => ({
        cle: e.cle,
        libelle: e.libelle,
        triable: true,
        priorite: 2,
        largeurMin: e.cle === 'protection_jeunesse' ? 130 : 95,
        tronquer: false,
        valeurTri: (p) => (p.titulaire ? etapeDe(p, e.cle)?.termine === true : null),
    })),
];

// ---- Ajout d'un servant(e) sur un nouveau rôle (fenêtre modale) ----
const ajoutOuvert = ref(false);
const modeNouveauServant = ref(false);

const form = useForm({
    shift_template_position_id: '',
    servant_id: '',
    nouveau_servant: {
        nom: '',
        prenom: '',
        genre: '',
        telephone: '',
    },
});

const optionsServants = computed(() => props.servantsDisponibles.map((s) => ({
    value: s.id,
    label: s.nom_complet,
    hint: s.role_actuel ? `déjà ${s.role_actuel} sur ce Shift, sera déplacé` : null,
})));

// Une seule recherche (SearchableSelect) : si la saisie correspond à un
// servant existant, on l'affecte directement (déplacement s'il est déjà
// sur ce Shift) ; sinon "+ Créer" bascule vers la création à la volée.
const demarrerNouveauServant = (texte) => {
    const [prenom, ...reste] = texte.split(/\s+/);
    form.nouveau_servant.prenom = prenom ?? '';
    form.nouveau_servant.nom = reste.join(' ');
    modeNouveauServant.value = true;
};

const reinitialiserRechercheServant = () => {
    form.servant_id = '';
    modeNouveauServant.value = false;
    form.nouveau_servant = { nom: '', prenom: '', genre: '', telephone: '' };
};

const ouvrirAjout = () => {
    form.reset();
    form.clearErrors();
    reinitialiserRechercheServant();
    ajoutOuvert.value = true;
};

// Le nouveau titulaire apparaît dans le tableau : on y amène la vue pour
// signaler que l'ajout a bien eu lieu.
const scrollerVersPoste = (positionId) => {
    document.querySelector(`[data-position-id="${positionId}"]`)
        ?.closest('tr, li')
        ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const ajouterServant = () => {
    const avant = new Set(props.positions.map((p) => p.id));

    form.transform((data) => ({
        shift_template_position_id: data.shift_template_position_id,
        ...(modeNouveauServant.value
            ? { nouveau_servant: data.nouveau_servant }
            : { servant_id: data.servant_id }),
    })).post(route('shifts.positions.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            reinitialiserRechercheServant();
            ajoutOuvert.value = false;
            const nouveau = props.positions.find((p) => !avant.has(p.id));
            if (nouveau) nextTick(() => scrollerVersPoste(nouveau.id));
        },
    });
};

// ---- Modification d'une affectation (fenêtre modale, plus d'édition en ligne) ----
const idEnEdition = ref(null);
// Relu dans les props à chaque réponse : les bascules d'étapes s'y reflètent.
const enEdition = computed(() => props.positions.find((p) => p.id === idEnEdition.value && p.titulaire) ?? null);
const editer = (p) => (idEnEdition.value = p.id);
const fermerEdition = () => (idEnEdition.value = null);

// ---- Affectation d'un servant(e) à un rôle vacant (fenêtre modale) ----
const idAAffecter = ref(null);
const aAffecter = computed(() => props.positions.find((p) => p.id === idAAffecter.value && !p.titulaire) ?? null);
const affectation = useForm({ servant_id: '' });

const ouvrirAffectation = (p) => {
    affectation.reset();
    affectation.clearErrors();
    idAAffecter.value = p.id;
};
const fermerAffectation = () => (idAAffecter.value = null);

const affecter = () => {
    const p = aAffecter.value;
    if (!p || !affectation.servant_id) return;
    affectation.post(route('shifts.positions.assign', [props.shift.id, p.id]), {
        preserveScroll: true,
        onSuccess: fermerAffectation,
    });
};

// ---- Retrait / suppression ----
const retirerServant = async (p) => {
    if (!(await confirmer(`Retirer ${p.titulaire.nom_complet} du rôle « ${p.nom} » ?`, { title: 'Retirer du rôle', danger: true }))) return;
    router.delete(route('shifts.positions.unassign', [props.shift.id, p.id, p.assignment_id]), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

const supprimerPoste = async (p) => {
    if (!(await confirmer(`Supprimer le rôle « ${p.nom} » ?`, { title: 'Supprimer le rôle', danger: true }))) return;
    router.delete(route('shifts.positions.destroy', [props.shift.id, p.id]), {
        preserveScroll: true,
    });
};

const actionsDe = (p) => (p.titulaire
    ? [{ cle: 'retirer', libelle: 'Retirer du rôle', icone: UserMinus, danger: true }]
    : [{ cle: 'supprimer', libelle: 'Supprimer le rôle', icone: Trash2, danger: true }]);

const agir = (p, cle) => ({ retirer: retirerServant, supprimer: supprimerPoste }[cle]?.(p));

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100';
const classeBoutonIcone = 'inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700';
</script>

<template>
    <Head :title="`Shift : ${shift.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Shifts', href: route('shifts.index') }, { label: shift.nom }]">
        <template #header>
            <div class="flex min-w-0 items-center justify-between gap-4">
                <h2 class="min-w-0 truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" :title="shift.nom">
                    {{ shift.nom }}
                </h2>
                <Link
                    v-if="!lectureSeule"
                    :href="route('shifts.edit', shift.id)"
                    class="inline-flex min-h-[44px] shrink-0 items-center rounded text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                >
                    Modifier le Shift
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <Link
                :href="route('shifts.index')"
                class="inline-flex min-h-[44px] items-center rounded text-sm text-neutral-600 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-400 dark:hover:text-neutral-100"
            >← Retour</Link>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Jour</dt>
                        <dd class="capitalize text-neutral-900 dark:text-neutral-100">{{ shift.jour }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Horaire</dt>
                        <dd class="text-neutral-900 dark:text-neutral-100">{{ shift.heure_debut }} - {{ shift.heure_fin }}</dd>
                    </div>
                </dl>
            </div>

            <section aria-labelledby="titre-roles" class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 id="titre-roles" class="text-lg font-medium text-neutral-900 dark:text-neutral-100">Rôles du Shift</h3>
                    <PrimaryButton v-if="!lectureSeule && postesDisponibles.length > 0" type="button" class="min-h-[44px]" @click="ouvrirAjout">
                        <Plus class="h-4 w-4" aria-hidden="true" />
                        Ajouter un servant(e)
                    </PrimaryButton>
                </div>

                <p v-if="positions.length === 0" class="rounded-xl bg-white p-6 text-sm text-neutral-600 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:text-neutral-400 dark:ring-neutral-700">
                    Aucun rôle pour ce Shift pour le moment.
                </p>

                <template v-else>
                    <SearchInput
                        v-model="recherche"
                        placeholder="Rechercher un rôle ou un titulaire…"
                        label="Rechercher un rôle ou un titulaire"
                    />

                    <DataTable
                        :colonnes="colonnes"
                        :lignes="positionsFiltrees"
                        :legende="`Rôles et titulaires du Shift ${shift.nom}`"
                        :filtre-actif="recherche.trim() !== ''"
                        :compteur="compteur"
                        :message-aucun-resultat="`Aucun résultat pour « ${recherche.trim()} ».`"
                    >
                        <template #cellule-nom="{ ligne }">
                            <span :data-position-id="ligne.id" class="[overflow-wrap:anywhere]">{{ ligne.nom }}</span>
                        </template>
                        <template #cellule-titulaire="{ ligne }">
                            <span v-if="ligne.titulaire" :title="ligne.titulaire.nom_complet">{{ ligne.titulaire.nom_complet }}</span>
                            <span v-else class="font-medium text-warning-700 dark:text-warning-300">Rôle vacant</span>
                        </template>
                        <template #cellule-protection_jeunesse="{ ligne }">
                            <template v-if="ligne.titulaire">
                                <Badge v-if="etapeDe(ligne, 'protection_jeunesse')?.termine" variant="success">Oui</Badge>
                                <Badge v-else variant="neutral">Non</Badge>
                            </template>
                            <span v-else class="text-neutral-400">—</span>
                        </template>
                        <template #cellule-badge="{ ligne }">
                            <template v-if="ligne.titulaire">
                                <Badge v-if="etapeDe(ligne, 'badge')?.termine" variant="success">Oui</Badge>
                                <Badge v-else variant="neutral">Non</Badge>
                            </template>
                            <span v-else class="text-neutral-400">—</span>
                        </template>
                        <template #cellule-photo="{ ligne }">
                            <template v-if="ligne.titulaire">
                                <Badge v-if="etapeDe(ligne, 'photo')?.termine" variant="success">Oui</Badge>
                                <Badge v-else variant="neutral">Non</Badge>
                            </template>
                            <span v-else class="text-neutral-400">—</span>
                        </template>

                        <template v-if="!lectureSeule" #actions="{ ligne, mode }">
                            <template v-if="ligne.titulaire">
                                <button
                                    v-if="mode === 'tableau'"
                                    type="button"
                                    :class="classeBoutonIcone"
                                    :aria-label="`Modifier l'affectation de ${ligne.titulaire.nom_complet} (${ligne.nom})`"
                                    :title="`Modifier l'affectation de ${ligne.titulaire.nom_complet}`"
                                    @click="editer(ligne)"
                                >
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier l'affectation de ${ligne.titulaire.nom_complet} (${ligne.nom})`" @click="editer(ligne)">
                                    <Pencil class="h-4 w-4" aria-hidden="true" />
                                    Modifier
                                </SecondaryButton>
                            </template>
                            <template v-else-if="servantsDisponibles.length">
                                <button
                                    v-if="mode === 'tableau'"
                                    type="button"
                                    :class="classeBoutonIcone"
                                    :aria-label="`Affecter un servant(e) au rôle ${ligne.nom}`"
                                    :title="`Affecter un servant(e) au rôle ${ligne.nom}`"
                                    @click="ouvrirAffectation(ligne)"
                                >
                                    <UserPlus class="h-4 w-4" aria-hidden="true" />
                                </button>
                                <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Affecter un servant(e) au rôle ${ligne.nom}`" @click="ouvrirAffectation(ligne)">
                                    <UserPlus class="h-4 w-4" aria-hidden="true" />
                                    Affecter
                                </SecondaryButton>
                            </template>
                            <ActionsMenu
                                :libelle="`Autres actions pour le rôle ${ligne.nom}`"
                                :actions="actionsDe(ligne)"
                                @choisir="(cle) => agir(ligne, cle)"
                            />
                        </template>
                    </DataTable>
                </template>
            </section>
        </div>

        <!-- ===== Ajout d'un servant(e) sur un rôle ===== -->
        <Modal v-if="!lectureSeule" :show="ajoutOuvert" max-width="xl" labelledby="titre-ajout-servant" @close="ajoutOuvert = false">
            <form class="space-y-4 p-6" @submit.prevent="ajouterServant">
                <h2 id="titre-ajout-servant" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Ajouter un servant(e)</h2>
                <div>
                    <InputLabel for="recherche_servant" value="Servant(e)" />
                    <SearchableSelect
                        id="recherche_servant"
                        v-model="form.servant_id"
                        :options="optionsServants"
                        :allow-create="true"
                        placeholder="Rechercher un servant(e)…"
                        class="mt-1 min-h-[44px]"
                        @update:model-value="modeNouveauServant = false"
                        @create="demarrerNouveauServant"
                    >
                        <template #create="{ query }">+ Créer « {{ query }} » comme nouveau servant(e)</template>
                    </SearchableSelect>
                    <InputError class="mt-1" :message="form.errors.servant_id" />
                </div>

                <div>
                    <InputLabel for="shift_template_position_id" value="Rôle" />
                    <select id="shift_template_position_id" v-model="form.shift_template_position_id" :class="classeChamp" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="p in postesDisponibles" :key="p.id" :value="p.id">{{ p.nom }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.shift_template_position_id" />
                </div>

                <fieldset v-if="modeNouveauServant" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <legend class="mb-2 text-sm font-medium text-neutral-800 dark:text-neutral-200">Nouveau servant(e)</legend>
                    <div>
                        <InputLabel for="nouveau_prenom" value="Prénom" />
                        <TextInput id="nouveau_prenom" v-model="form.nouveau_servant.prenom" type="text" :class="classeChamp" required />
                        <InputError class="mt-1" :message="form.errors['nouveau_servant.prenom']" />
                    </div>
                    <div>
                        <InputLabel for="nouveau_nom" value="Nom" />
                        <TextInput id="nouveau_nom" v-model="form.nouveau_servant.nom" type="text" :class="classeChamp" required />
                        <InputError class="mt-1" :message="form.errors['nouveau_servant.nom']" />
                    </div>
                    <div>
                        <InputLabel for="nouveau_genre" value="Genre" />
                        <select id="nouveau_genre" v-model="form.nouveau_servant.genre" :class="classeChamp">
                            <option value="">Non précisé</option>
                            <option value="homme">Homme</option>
                            <option value="femme">Femme</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors['nouveau_servant.genre']" />
                    </div>
                    <div>
                        <InputLabel for="nouveau_telephone" value="Téléphone (optionnel)" />
                        <TextInput id="nouveau_telephone" v-model="form.nouveau_servant.telephone" type="text" :class="classeChamp" />
                        <InputError class="mt-1" :message="form.errors['nouveau_servant.telephone']" />
                    </div>
                </fieldset>

                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="ajoutOuvert = false">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing || (!form.servant_id && !modeNouveauServant)">Ajouter</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- ===== Modification d'une affectation ===== -->
        <EtapesAffectationModal v-if="!lectureSeule" :position="enEdition" @close="fermerEdition">
            <template #actions="{ position }">
                <DangerButton type="button" class="min-h-[44px]" @click="retirerServant(position)">
                    <UserMinus class="h-4 w-4" aria-hidden="true" />
                    Retirer du rôle
                </DangerButton>
            </template>
        </EtapesAffectationModal>

        <!-- ===== Affectation à un rôle vacant ===== -->
        <Modal v-if="!lectureSeule" :show="aAffecter !== null" max-width="lg" labelledby="titre-affectation" @close="fermerAffectation">
            <form v-if="aAffecter" class="space-y-4 p-6" @submit.prevent="affecter">
                <h2 id="titre-affectation" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Affecter un servant(e) : {{ aAffecter.nom }}
                </h2>
                <div>
                    <InputLabel for="affectation_servant" value="Servant(e)" />
                    <SearchableSelect
                        id="affectation_servant"
                        v-model="affectation.servant_id"
                        :options="optionsServants"
                        placeholder="Rechercher un servant(e)…"
                        class="mt-1 min-h-[44px]"
                    />
                    <InputError class="mt-1" :message="affectation.errors.servant_id" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerAffectation">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="affectation.processing || !affectation.servant_id">Affecter</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
