<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import Badge from '@/Components/Badge.vue';
import Checkbox from '@/Components/Checkbox.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Lock, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    roles: Array,
});

// Tri client (liste complète) ; ordre par défaut : nom (ordre du serveur).
const colonnes = [
    { cle: 'nom', libelle: 'Rôle', triable: true, principale: true, priorite: 1, largeurMin: 200, tronquer: false },
    { cle: 'gere_shifts', libelle: 'Gère des shifts', triable: true, priorite: 2, largeurMin: 130, valeur: (r) => (r.gere_shifts ? 'Oui' : 'Non') },
    { cle: 'type', libelle: 'Type', triable: true, priorite: 3, largeurMin: 120, valeur: (r) => (r.protege ? 'Protégé' : 'Personnalisé') },
    { cle: 'description', libelle: 'Description', triable: true, priorite: 4, largeurMin: 220 },
    { cle: 'slug', libelle: 'Clé technique', triable: true, priorite: 5, largeurMin: 160 },
];

const compteur = computed(() => `${props.roles.length} rôle${props.roles.length > 1 ? 's' : ''}`);

// ---- Création (fenêtre modale) ----
const creationOuverte = ref(false);
const createForm = useForm({ nom: '', description: '', gere_shifts: false });

const ouvrirCreation = () => {
    createForm.clearErrors();
    creationOuverte.value = true;
};

const creer = () => {
    createForm.post(route('settings.roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            creationOuverte.value = false;
        },
    });
};

// ---- Modification (fenêtre modale, plus d'édition en ligne) ----
const enEdition = ref(null);
const form = useForm({ nom: '', description: '', gere_shifts: false });

const editer = (role) => {
    form.defaults({ nom: role.nom, description: role.description ?? '', gere_shifts: Boolean(role.gere_shifts) });
    form.reset();
    form.clearErrors();
    enEdition.value = role;
};

const fermerEdition = () => (enEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    form.put(route('settings.roles.update', enEdition.value.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

// ---- Suppression (menu ⋯) : rôles protégés ou encore attribués non supprimables ----
// Sans action possible, un bouton indisponible (focalisable) donne la raison
// au lieu d'un menu dont toutes les entrées seraient désactivées.
const raisonNonSupprimable = (role) => {
    if (role.protege) return 'Rôle protégé : il ne peut pas être supprimé.';
    if (role.utilise) return 'Rôle encore attribué à des comptes';
    return null;
};

const actionsDe = () => [{ cle: 'supprimer', libelle: 'Supprimer le rôle', icone: Trash2, danger: true }];

const supprimer = async (role) => {
    if (!(await confirmer(`Supprimer le rôle « ${role.nom} » ?`, { danger: true }))) return;
    router.delete(route('settings.roles.destroy', role.id), { preserveScroll: true });
};

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500';
</script>

<template>
    <Head title="Rôles" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Rôles' }]">
        <template #header>
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <h2 class="min-w-0 truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Paramètres — Rôles">
                    Paramètres — Rôles
                </h2>
                <PrimaryButton type="button" class="min-h-[44px] shrink-0" @click="ouvrirCreation">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nouveau rôle
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-4">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Les rôles marqués « protégé » (Conseil du Temple, Coordonnateur…) portent des permissions
                codées dans l'application et ne peuvent pas être supprimés. Un rôle personnalisé n'a accès qu'au tableau de
                bord et au profil tant qu'aucun accès spécifique ne lui est ouvert.
            </p>

            <DataTable
                :colonnes="colonnes"
                :lignes="roles"
                legende="Rôles de l'application"
                :compteur="compteur"
                message-vide="Aucun rôle."
                largeur-actions="8.5rem"
            >
                <template #cellule-nom="{ ligne }">
                    <span class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <span class="min-w-0 [overflow-wrap:anywhere]">{{ ligne.nom }}</span>
                        <Badge v-if="ligne.protege" variant="info">Protégé</Badge>
                        <Badge v-if="ligne.modifiable === false" variant="neutral">
                            <Lock class="h-3 w-3" aria-hidden="true" />
                            Verrouillé
                        </Badge>
                    </span>
                </template>
                <template #cellule-gere_shifts="{ ligne }">
                    <Badge v-if="ligne.gere_shifts" variant="success">Gère des shifts</Badge>
                    <span v-else class="text-neutral-500 dark:text-neutral-400">Non</span>
                </template>
                <template #cellule-slug="{ ligne }">
                    <span class="text-xs uppercase text-neutral-600 dark:text-neutral-400" :title="ligne.slug">{{ ligne.slug }}</span>
                </template>

                <template #actions="{ ligne, mode }">
                    <template v-if="ligne.modifiable !== false">
                        <button
                            v-if="mode === 'tableau'"
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                            :aria-label="`Modifier le rôle ${ligne.nom}`"
                            :title="`Modifier le rôle ${ligne.nom}`"
                            @click="editer(ligne)"
                        >
                            <Pencil class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier le rôle ${ligne.nom}`" @click="editer(ligne)">
                            <Pencil class="h-4 w-4" aria-hidden="true" />
                            Modifier
                        </SecondaryButton>
                    </template>
                    <ActionsMenu
                        v-if="!raisonNonSupprimable(ligne)"
                        :libelle="`Autres actions pour le rôle ${ligne.nom}`"
                        :actions="actionsDe(ligne)"
                        @choisir="() => supprimer(ligne)"
                    />
                    <button
                        v-else
                        type="button"
                        aria-disabled="true"
                        class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-lg text-neutral-400 ring-1 ring-neutral-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-500 dark:ring-neutral-600"
                        :aria-label="`Supprimer le rôle ${ligne.nom} : impossible. ${raisonNonSupprimable(ligne)}`"
                        :title="raisonNonSupprimable(ligne)"
                    >
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </button>
                </template>
            </DataTable>
        </div>

        <!-- ===== Création d'un rôle ===== -->
        <Modal :show="creationOuverte" max-width="lg" labelledby="titre-creation-role" @close="creationOuverte = false">
            <form class="space-y-4 p-6" @submit.prevent="creer">
                <h2 id="titre-creation-role" class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">Nouveau rôle</h2>
                <div>
                    <InputLabel for="nouveau-nom" value="Nom du rôle" />
                    <TextInput id="nouveau-nom" v-model="createForm.nom" type="text" :class="classeChamp" placeholder="Ex. : Équipe du bureau" required />
                    <InputError class="mt-2" :message="createForm.errors.nom" />
                </div>
                <div>
                    <InputLabel for="nouvelle-description" value="Description (optionnel)" />
                    <textarea id="nouvelle-description" v-model="createForm.description" rows="3" :class="classeChamp"></textarea>
                    <InputError class="mt-2" :message="createForm.errors.description" />
                </div>
                <label class="flex min-h-[44px] items-start gap-3 text-sm text-neutral-700 dark:text-neutral-300">
                    <Checkbox v-model:checked="createForm.gere_shifts" class="mt-0.5 h-5 w-5" />
                    <span>Ce rôle gère des shifts (accès au dashboard coordinateur, peut être affecté comme responsable d'un Shift)</span>
                </label>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="creationOuverte = false">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="createForm.processing">Créer le rôle</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- ===== Modification d'un rôle ===== -->
        <Modal :show="enEdition !== null" max-width="lg" labelledby="titre-edition-role" @close="fermerEdition">
            <form v-if="enEdition" class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-role" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier le rôle « {{ enEdition.nom }} »
                </h2>
                <div>
                    <InputLabel for="edition-role-nom" value="Nom du rôle" />
                    <TextInput id="edition-role-nom" v-model="form.nom" type="text" :class="classeChamp" required />
                    <InputError class="mt-2" :message="form.errors.nom" />
                </div>
                <div>
                    <InputLabel for="edition-role-description" value="Description (optionnel)" />
                    <textarea id="edition-role-description" v-model="form.description" rows="3" :class="classeChamp"></textarea>
                    <InputError class="mt-2" :message="form.errors.description" />
                </div>
                <label class="flex min-h-[44px] items-start gap-3 text-sm text-neutral-700 dark:text-neutral-300">
                    <Checkbox v-model:checked="form.gere_shifts" class="mt-0.5 h-5 w-5" />
                    <span>Ce rôle gère des shifts</span>
                </label>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
