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
    horaires: Array,
});

// Tri client (liste complète) ; ordre par défaut : heure de début (serveur).
const colonnes = [
    { cle: 'nom', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 200 },
    { cle: 'heure_debut', libelle: 'Début', triable: true, priorite: 1, largeurMin: 104, largeur: '6.5rem' },
    { cle: 'heure_fin', libelle: 'Fin', triable: true, priorite: 1, largeurMin: 104, largeur: '6.5rem' },
];

const compteur = computed(() => `${props.horaires.length} horaire${props.horaires.length > 1 ? 's' : ''}`);

// ---- Ajout ----
const form = useForm({ nom: '', heure_debut: '06:30', heure_fin: '12:30' });

const ajouter = () => {
    form.post(route('settings.horaires.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

// ---- Modification (fenêtre modale) ----
const enEdition = ref(null);
const edition = useForm({ nom: '', heure_debut: '', heure_fin: '' });

const editer = (horaire) => {
    edition.defaults({ nom: horaire.nom, heure_debut: horaire.heure_debut, heure_fin: horaire.heure_fin });
    edition.reset();
    edition.clearErrors();
    enEdition.value = horaire;
};

const fermerEdition = () => (enEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    edition.put(route('settings.horaires.update', enEdition.value.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

// ---- Suppression (menu ⋯) ----
const actions = [{ cle: 'supprimer', libelle: 'Supprimer', icone: Trash2, danger: true }];

const supprimer = async (horaire) => {
    if (!(await confirmer(`Supprimer l'horaire « ${horaire.nom} » ?`, { danger: true }))) return;
    router.delete(route('settings.horaires.destroy', horaire.id), { preserveScroll: true });
};

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500';
</script>

<template>
    <Head title="Horaires" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Horaires' }]">
        <template #header>
            <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Paramètres — Horaires">
                Paramètres — Horaires
            </h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <form class="grid grid-cols-1 gap-4 rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:grid-cols-4 sm:p-6" @submit.prevent="ajouter">
                <div class="sm:col-span-2">
                    <InputLabel for="nom" value="Nom" />
                    <TextInput id="nom" v-model="form.nom" type="text" :class="classeChamp" placeholder="Ex: Matin" required />
                    <InputError class="mt-1" :message="form.errors.nom" />
                </div>
                <div>
                    <InputLabel for="heure_debut" value="Début" />
                    <TextInput id="heure_debut" v-model="form.heure_debut" type="time" :class="classeChamp" required />
                    <InputError class="mt-1" :message="form.errors.heure_debut" />
                </div>
                <div>
                    <InputLabel for="heure_fin" value="Fin" />
                    <TextInput id="heure_fin" v-model="form.heure_fin" type="time" :class="classeChamp" required />
                    <InputError class="mt-1" :message="form.errors.heure_fin" />
                </div>
                <div class="flex justify-end sm:col-span-4">
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <DataTable
                :colonnes="colonnes"
                :lignes="horaires"
                legende="Horaires de l'organisation"
                :compteur="compteur"
                message-vide="Aucun horaire enregistré."
            >
                <template #actions="{ ligne, mode }">
                    <button
                        v-if="mode === 'tableau'"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                        :aria-label="`Modifier l'horaire ${ligne.nom}`"
                        :title="`Modifier l'horaire ${ligne.nom}`"
                        @click="editer(ligne)"
                    >
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier l'horaire ${ligne.nom}`" @click="editer(ligne)">
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                        Modifier
                    </SecondaryButton>
                    <ActionsMenu :libelle="`Autres actions pour l'horaire ${ligne.nom}`" :actions="actions" @choisir="() => supprimer(ligne)" />
                </template>
            </DataTable>
        </div>

        <!-- ===== Modification d'un horaire ===== -->
        <Modal :show="enEdition !== null" max-width="lg" labelledby="titre-edition-horaire" @close="fermerEdition">
            <form v-if="enEdition" class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-horaire" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier l'horaire « {{ enEdition.nom }} »
                </h2>
                <div>
                    <InputLabel for="edition-horaire-nom" value="Nom" />
                    <TextInput id="edition-horaire-nom" v-model="edition.nom" type="text" :class="classeChamp" required />
                    <InputError class="mt-1" :message="edition.errors.nom" />
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="edition-horaire-debut" value="Début" />
                        <TextInput id="edition-horaire-debut" v-model="edition.heure_debut" type="time" :class="classeChamp" required />
                        <InputError class="mt-1" :message="edition.errors.heure_debut" />
                    </div>
                    <div>
                        <InputLabel for="edition-horaire-fin" value="Fin" />
                        <TextInput id="edition-horaire-fin" v-model="edition.heure_fin" type="time" :class="classeChamp" required />
                        <InputError class="mt-1" :message="edition.errors.heure_fin" />
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="edition.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
