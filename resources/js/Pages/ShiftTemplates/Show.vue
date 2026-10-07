<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { ChevronUp, ChevronDown, GripVertical, Pencil, Trash2 } from '@lucide/vue';

const props = defineProps({
    template: Object,
    positions: Array,
});

const { confirmer } = useConfirm();

// Copie locale pour un retour visuel immédiat pendant le glisser-déposer ;
// resynchronisée à chaque réponse du serveur (props.positions).
const positionsAffichees = ref([...props.positions]);
watch(() => props.positions, (val) => (positionsAffichees.value = [...val]));

// L'ordre des postes est un ordre MÉTIER (ordre d'affichage sur les Shifts
// créés depuis ce modèle) : aucun tri n'est proposé, seul le déplacement
// manuel (glisser-déposer, ou Monter / Descendre au clavier et au toucher).
// Un couple homme/femme (même « bloc », calculé par le serveur) forme un seul
// bloc : la femme reste sous l'homme, le Scelleur se déplace seul.
const blocs = computed(() => {
    const groupes = [];
    for (const p of positionsAffichees.value) {
        const dernier = groupes[groupes.length - 1];
        if (dernier && dernier.bloc === p.bloc) {
            dernier.postes.push(p);
        } else {
            groupes.push({ bloc: p.bloc, numero: p.numero, postes: [p] });
        }
    }
    return groupes;
});

const dernierBloc = computed(() => blocs.value.length - 1);
const nomsBloc = (bloc) => bloc.postes.map((p) => p.nom).join(' / ');

// ---- Glisser-déposer (souris) ----
const blocGlisse = ref(null);
const blocSurvole = ref(null);

const onDrop = (bloc) => {
    blocSurvole.value = null;
    const source = blocGlisse.value;
    blocGlisse.value = null;
    if (source === null || source === bloc.bloc) {
        return;
    }

    const groupes = blocs.value.map((b) => b.postes);
    const de = groupes.findIndex((g) => g[0].bloc === source);
    const vers = groupes.findIndex((g) => g[0].bloc === bloc.bloc);
    const [deplace] = groupes.splice(de, 1);
    groupes.splice(vers, 0, deplace);

    const items = groupes.flatMap((g, i) => g.map((p) => ({ ...p, bloc: i, numero: i + 1 })));
    positionsAffichees.value = items;

    router.patch(route('shift-templates.positions.reorder', props.template.id), {
        positions: items.map((p) => p.id),
    }, { preserveScroll: true });
};

// ---- Monter / Descendre (clavier, toucher) : le focus suit le bloc déplacé ----
const annonce = ref('');

const deplacerBloc = (bloc, direction) => {
    const premier = bloc.postes[0];
    router.patch(route('shift-templates.positions.move', [props.template.id, premier.id]), { direction }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: async () => {
            await nextTick();
            const nouveau = blocs.value.find((b) => b.postes.some((p) => p.id === premier.id));
            if (!nouveau) return;
            annonce.value = `${nomsBloc(nouveau)} : position ${nouveau.numero} sur ${blocs.value.length}.`;
            const cible = document.querySelector(`[data-deplacer="${premier.id}-${direction}"]:not(:disabled)`)
                ?? document.querySelector(`[data-deplacer^="${premier.id}-"]:not(:disabled)`);
            cible?.focus();
        },
    });
};

// ---- Ajout ----
const form = useForm({
    nom: '',
});

const ajouterPoste = () => {
    form.post(route('shift-templates.positions.store', props.template.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

// ---- Modification d'un poste (fenêtre modale, plus d'édition en ligne) ----
const posteEnEdition = ref(null);
const edition = useForm({ nom: '' });

const editerPoste = (position) => {
    edition.defaults({ nom: position.nom });
    edition.reset();
    edition.clearErrors();
    posteEnEdition.value = position;
};

const fermerEdition = () => (posteEnEdition.value = null);

const enregistrerPoste = () => {
    const position = posteEnEdition.value;
    if (!position) return;
    edition.put(route('shift-templates.positions.update', [props.template.id, position.id]), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

const supprimerPoste = async (position) => {
    if (!(await confirmer(`Supprimer le poste « ${position.nom} » du modèle ?`, { danger: true }))) return;
    router.delete(route('shift-templates.positions.destroy', [props.template.id, position.id]), {
        preserveScroll: true,
    });
};

const actionsPoste = [
    { cle: 'modifier', libelle: 'Modifier le nom', icone: Pencil },
    { cle: 'retirer', libelle: 'Retirer du modèle', icone: Trash2, danger: true },
];

const agir = (position, cle) => (cle === 'modifier' ? editerPoste(position) : supprimerPoste(position));

const classeDeplacer = 'inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-600 ring-1 ring-neutral-200 transition hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent dark:text-neutral-300 dark:ring-neutral-600 dark:hover:bg-neutral-700 dark:hover:text-neutral-100';
</script>

<template>
    <Head :title="template.nom" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de Shift', href: route('shift-templates.index') }, { label: template.nom }]">
        <template #header>
            <!-- En-tête de hauteur fixe (h-16) : le nom passe sur deux lignes
                 au plus (coupure même au milieu d'un mot très long), puis
                 s'abrège ; le nom complet reste dans title. Sous sm, « Modifier » quitte
                 l'en-tête (lien dans la page) pour laisser la place au nom. -->
            <div class="flex min-w-0 items-center justify-between gap-2 sm:gap-3">
                <h2 class="line-clamp-2 min-w-0 break-words text-base font-semibold leading-tight text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100 sm:text-xl" :title="template.nom">
                    {{ template.nom }}
                </h2>
                <Link
                    :href="route('shift-templates.edit', template.id)"
                    class="hidden min-h-[44px] shrink-0 items-center justify-center rounded px-2 text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light sm:inline-flex"
                >
                    Modifier<span class="sr-only"> le modèle {{ template.nom }}</span>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <Link
                :href="route('shift-templates.index')"
                class="inline-flex min-h-[44px] items-center rounded text-sm text-neutral-600 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-400 dark:hover:text-neutral-100"
            >← Retour</Link>

            <Link
                :href="route('shift-templates.edit', template.id)"
                class="flex min-h-[44px] items-center justify-center rounded-lg border border-neutral-300 px-4 text-sm font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:border-neutral-600 sm:hidden"
            >
                <Pencil aria-hidden="true" class="mr-2 h-4 w-4" />Modifier<span class="sr-only"> le modèle {{ template.nom }}</span>
            </Link>

            <div v-if="template.description" class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6">
                <p class="break-words text-neutral-600 [overflow-wrap:anywhere] dark:text-neutral-400">{{ template.description }}</p>
            </div>

            <section class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6" aria-labelledby="titre-postes-modele">
                <h3 id="titre-postes-modele" class="mb-4 text-lg font-medium text-neutral-900 dark:text-neutral-100">Postes du modèle</h3>

                <form class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start" @submit.prevent="ajouterPoste">
                    <div class="min-w-0 flex-1">
                        <InputLabel for="nouveau-poste" value="Nouveau poste" class="sr-only" />
                        <TextInput
                            id="nouveau-poste"
                            v-model="form.nom"
                            type="text"
                            class="block min-h-[44px] w-full"
                            placeholder="Ex: Coordonnateur Adjoint"
                            required
                        />
                        <InputError class="mt-1" :message="form.errors.nom" />
                    </div>
                    <PrimaryButton class="min-h-[44px] justify-center" :disabled="form.processing">Ajouter</PrimaryButton>
                </form>

                <p v-if="blocs.length > 1" id="aide-ordre-postes" class="mb-2 text-xs text-neutral-600 dark:text-neutral-400">
                    L'ordre des postes est celui des Shifts créés depuis ce modèle. Glissez un bloc (poignée à gauche) ou utilisez
                    les boutons Monter / Descendre. Un poste féminin reste toujours sous son poste masculin : le couple se déplace ensemble.
                </p>
                <div class="sr-only" aria-live="polite" aria-atomic="true">{{ annonce }}</div>

                <p v-if="blocs.length === 0" class="py-6 text-center text-neutral-600 dark:text-neutral-400">
                    Aucun poste défini pour ce modèle.
                </p>
                <ol v-else class="divide-y divide-neutral-100 dark:divide-neutral-700" aria-label="Postes du modèle, dans l'ordre">
                    <li
                        v-for="bloc in blocs"
                        :key="bloc.postes[0].id"
                        class="flex min-w-0 items-start gap-2 py-3 sm:items-center"
                        :class="{ 'opacity-40': blocGlisse === bloc.bloc, 'bg-primary-50/60 dark:bg-primary-900/20': blocSurvole === bloc.bloc && blocGlisse !== bloc.bloc }"
                        draggable="true"
                        @dragstart="blocGlisse = bloc.bloc"
                        @dragover.prevent="blocSurvole = bloc.bloc"
                        @dragleave="blocSurvole = null"
                        @drop="onDrop(bloc)"
                        @dragend="blocGlisse = null; blocSurvole = null"
                    >
                        <GripVertical class="mt-3.5 h-4 w-4 shrink-0 cursor-grab text-neutral-400 active:cursor-grabbing sm:mt-0" aria-hidden="true" />
                        <span class="mt-3 w-6 shrink-0 text-right text-sm font-medium tabular-nums text-neutral-600 dark:text-neutral-400 sm:mt-0">{{ bloc.numero }}.</span>

                        <ul class="min-w-0 flex-1 space-y-1" role="list">
                            <li v-for="position in bloc.postes" :key="position.id" class="flex min-w-0 items-center gap-2">
                                <span class="min-w-0 flex-1 truncate text-neutral-900 dark:text-neutral-100" :title="position.nom">{{ position.nom }}</span>
                                <ActionsMenu
                                    :libelle="`Actions pour le poste ${position.nom}`"
                                    :actions="actionsPoste"
                                    @choisir="(cle) => agir(position, cle)"
                                />
                            </li>
                        </ul>

                        <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                            <button
                                type="button"
                                :data-deplacer="`${bloc.postes[0].id}-haut`"
                                :disabled="bloc.bloc === 0"
                                :class="classeDeplacer"
                                :aria-label="`Monter ${nomsBloc(bloc)} (position ${bloc.numero} sur ${blocs.length})`"
                                title="Monter"
                                @click="deplacerBloc(bloc, 'haut')"
                            >
                                <ChevronUp class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                :data-deplacer="`${bloc.postes[0].id}-bas`"
                                :disabled="bloc.bloc === dernierBloc"
                                :class="classeDeplacer"
                                :aria-label="`Descendre ${nomsBloc(bloc)} (position ${bloc.numero} sur ${blocs.length})`"
                                title="Descendre"
                                @click="deplacerBloc(bloc, 'bas')"
                            >
                                <ChevronDown class="h-4 w-4" aria-hidden="true" />
                            </button>
                        </div>
                    </li>
                </ol>
            </section>
        </div>

        <!-- ===== Modification d'un poste ===== -->
        <Modal :show="posteEnEdition !== null" max-width="lg" labelledby="titre-edition-poste" @close="fermerEdition">
            <form v-if="posteEnEdition" class="p-6" @submit.prevent="enregistrerPoste">
                <h2 id="titre-edition-poste" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier le poste « {{ posteEnEdition.nom }} »
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    La correction s'applique aussi aux Shifts qui utilisent déjà ce poste.
                </p>
                <div class="mt-4">
                    <InputLabel for="edition-poste-nom" value="Nom du poste" />
                    <TextInput id="edition-poste-nom" v-model="edition.nom" type="text" class="mt-1 block min-h-[44px] w-full" required autocomplete="off" />
                    <InputError class="mt-2" :message="edition.errors.nom" />
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="edition.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
