<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Pencil, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    etapes: Array,
});

// L'ordre du parcours est un ordre MÉTIER (champ « ordre », modifiable dans la
// fenêtre de modification) : c'est le tri par défaut. Trier par nom ou par clé
// ne change que l'affichage ; « Ordre » rétablit l'ordre du parcours.
const colonnes = [
    { cle: 'ordre', libelle: 'Ordre', triable: true, priorite: 1, largeurMin: 96, largeur: '6rem', alignement: 'fin' },
    { cle: 'nom', libelle: 'Étape', triable: true, principale: true, priorite: 1, largeurMin: 200, tronquer: false },
    { cle: 'cle', libelle: 'Clé technique', triable: true, priorite: 3, largeurMin: 170 },
];

const compteur = computed(() => `${props.etapes.length} étape${props.etapes.length > 1 ? 's' : ''}`);

// ---- Ajout ----
const form = useForm({ cle: '', nom: '' });

const ajouter = () => {
    form.post(route('settings.workflow-steps.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

// ---- Modification (fenêtre modale, plus d'édition en ligne) ----
const enEdition = ref(null);
const editForm = useForm({ nom: '', ordre: 1 });

const editer = (etape) => {
    editForm.defaults({ nom: etape.nom, ordre: etape.ordre });
    editForm.reset();
    editForm.clearErrors();
    enEdition.value = etape;
};

const fermerEdition = () => (enEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    editForm.put(route('settings.workflow-steps.update', enEdition.value.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

// ---- Suppression (menu ⋯) ----
const actions = [{ cle: 'supprimer', libelle: 'Supprimer', icone: Trash2, danger: true }];

const supprimer = async (etape) => {
    if (!(await confirmer(`Supprimer l'étape « ${etape.nom} » ? Elle sera retirée du parcours de tous les servant(e)s.`, { danger: true }))) return;
    router.delete(route('settings.workflow-steps.destroy', etape.id), { preserveScroll: true });
};

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500';
</script>

<template>
    <Head title="Étapes du parcours" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Étapes du parcours' }]">
        <template #header>
            <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Paramètres — Étapes du parcours d'intégration">
                Paramètres — Étapes du parcours d'intégration
            </h2>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <form class="grid grid-cols-1 gap-4 rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:grid-cols-3 sm:p-6" @submit.prevent="ajouter">
                <div>
                    <InputLabel for="cle" value="Clé (technique)" />
                    <TextInput id="cle" v-model="form.cle" type="text" :class="classeChamp" placeholder="ex: entretien_final" required />
                    <InputError class="mt-1" :message="form.errors.cle" />
                </div>
                <div>
                    <InputLabel for="nom" value="Nom affiché" />
                    <TextInput id="nom" v-model="form.nom" type="text" :class="classeChamp" placeholder="Ex: Entretien final" required />
                    <InputError class="mt-1" :message="form.errors.nom" />
                </div>
                <div class="flex items-end justify-end sm:justify-start">
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <DataTable
                :colonnes="colonnes"
                :lignes="etapes"
                legende="Étapes du parcours d'intégration, dans l'ordre du parcours"
                :tri="{ cle: 'ordre', sens: 'asc' }"
                :compteur="compteur"
                message-vide="Aucune étape définie."
            >
                <template #cellule-cle="{ ligne }">
                    <span class="font-mono text-xs text-neutral-600 dark:text-neutral-400" :title="ligne.cle">{{ ligne.cle }}</span>
                </template>

                <template #actions="{ ligne, mode }">
                    <button
                        v-if="mode === 'tableau'"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                        :aria-label="`Modifier l'étape ${ligne.nom}`"
                        :title="`Modifier l'étape ${ligne.nom}`"
                        @click="editer(ligne)"
                    >
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier l'étape ${ligne.nom}`" @click="editer(ligne)">
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                        Modifier
                    </SecondaryButton>
                    <ActionsMenu :libelle="`Autres actions pour l'étape ${ligne.nom}`" :actions="actions" @choisir="() => supprimer(ligne)" />
                </template>
            </DataTable>
        </div>

        <!-- ===== Modification d'une étape ===== -->
        <Modal :show="enEdition !== null" max-width="lg" labelledby="titre-edition-etape" @close="fermerEdition">
            <form v-if="enEdition" class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-etape" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier l'étape « {{ enEdition.nom }} »
                </h2>
                <div>
                    <InputLabel for="edition-etape-nom" value="Nom affiché" />
                    <TextInput id="edition-etape-nom" v-model="editForm.nom" type="text" :class="classeChamp" required />
                    <InputError class="mt-1" :message="editForm.errors.nom" />
                </div>
                <div>
                    <InputLabel for="edition-etape-ordre" value="Position dans le parcours" />
                    <input
                        id="edition-etape-ordre"
                        v-model.number="editForm.ordre"
                        type="number"
                        min="1"
                        required
                        aria-describedby="edition-etape-ordre-aide"
                        :class="[classeChamp, 'sm:w-32']"
                    />
                    <p id="edition-etape-ordre-aide" class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">1 = première étape du parcours.</p>
                    <InputError class="mt-1" :message="editForm.errors.ordre" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="editForm.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
