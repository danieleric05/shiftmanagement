<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ActionsMenu from '@/Components/ActionsMenu.vue';
import Badge from '@/Components/Badge.vue';
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
    pieux: Array,
});

const typeLabel = { mission: 'Mission', district: 'District', pieu: 'Pieu' };
const typeVariant = { mission: 'info', district: 'warning', pieu: 'neutral' };
// Rang hiérarchique : le tri par type suit Mission → District → Pieu.
const rangType = { mission: 0, district: 1, pieu: 2 };

// Le parent attendu pour un type donné (une Mission n'a pas de parent).
const typeParentAttendu = { mission: null, district: 'mission', pieu: 'district' };

const parentsPossibles = (type, exclureId = null) => props.pieux.filter((p) => p.type === typeParentAttendu[type] && p.id !== exclureId);

const nomParent = (pieu) => props.pieux.find((p) => p.id === pieu.parent_id)?.nom ?? null;
const nombreEnfants = (pieu) => props.pieux.filter((p) => p.parent_id === pieu.id).length;

// Tri client (liste complète). Ordre par défaut (serveur) : type hiérarchique puis nom.
const colonnes = [
    { cle: 'nom', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 200, tronquer: false },
    { cle: 'type', libelle: 'Type', triable: true, priorite: 1, largeurMin: 110, valeurTri: (p) => rangType[p.type] ?? 9, valeur: (p) => typeLabel[p.type] ?? p.type },
    { cle: 'parent', libelle: 'Rattaché(e) à', triable: true, priorite: 2, largeurMin: 180, valeur: nomParent },
    { cle: 'enfants', libelle: 'Unités rattachées', triable: true, priorite: 3, largeurMin: 140, alignement: 'fin', valeur: nombreEnfants },
];

const compteur = computed(() => `${props.pieux.length} unité${props.pieux.length > 1 ? 's' : ''}`);

// ---- Ajout ----
const form = useForm({ nom: '', type: 'pieu', parent_id: '' });

const ajouter = () => {
    form.transform((data) => ({ ...data, parent_id: data.parent_id || null })).post(route('settings.pieux.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

// ---- Modification (fenêtre modale, plus d'édition en ligne) ----
const enEdition = ref(null);
const editForm = useForm({ nom: '', type: 'pieu', parent_id: '' });

const editer = (pieu) => {
    editForm.defaults({ nom: pieu.nom, type: pieu.type, parent_id: pieu.parent_id ?? '' });
    editForm.reset();
    editForm.clearErrors();
    enEdition.value = pieu;
};

const fermerEdition = () => (enEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    editForm.transform((data) => ({ ...data, parent_id: data.parent_id || null })).put(route('settings.pieux.update', enEdition.value.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
};

// ---- Suppression (menu ⋯) ----
// Unité avec des unités rattachées : bouton indisponible (focalisable) avec la raison.
const RAISON_ENFANTS = 'Cette unité a des unités rattachées : détachez-les avant de la supprimer.';
const actions = [{ cle: 'supprimer', libelle: 'Supprimer', icone: Trash2, danger: true }];

const supprimer = async (pieu) => {
    if (!(await confirmer(`Supprimer « ${pieu.nom} » ?`, { danger: true }))) return;
    router.delete(route('settings.pieux.destroy', pieu.id), { preserveScroll: true });
};

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500';
</script>

<template>
    <Head title="Pieux" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Pieux' }]">
        <template #header>
            <h2 class="truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Paramètres — Pieux, Districts & Missions">
                Paramètres — Pieux, Districts &amp; Missions
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Un servant peut être rattaché soit à un Pieu, soit directement à un District, soit directement à une
                Mission — utile quand l'information exacte n'est pas connue.
            </p>

            <form class="space-y-3 rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:p-6" @submit.prevent="ajouter">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <InputLabel for="nouveau-nom" value="Nom" />
                        <TextInput id="nouveau-nom" v-model="form.nom" type="text" :class="classeChamp" required />
                        <InputError class="mt-1" :message="form.errors.nom" />
                    </div>
                    <div>
                        <InputLabel for="nouveau-type" value="Type" />
                        <select id="nouveau-type" v-model="form.type" :class="classeChamp" @change="form.parent_id = ''">
                            <option value="mission">Mission</option>
                            <option value="district">District</option>
                            <option value="pieu">Pieu</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.type" />
                    </div>
                    <div v-if="form.type !== 'mission'">
                        <InputLabel for="nouveau-parent" :value="form.type === 'pieu' ? 'District parent (optionnel)' : 'Mission parente (optionnel)'" />
                        <select id="nouveau-parent" v-model="form.parent_id" :class="classeChamp">
                            <option value="">Aucun</option>
                            <option v-for="p in parentsPossibles(form.type)" :key="p.id" :value="p.id">{{ p.nom }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.parent_id" />
                    </div>
                </div>
                <div class="flex justify-end">
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <DataTable
                :colonnes="colonnes"
                :lignes="pieux"
                legende="Missions, districts et pieux"
                :compteur="compteur"
                message-vide="Aucune unité enregistrée."
            >
                <template #cellule-type="{ ligne }">
                    <Badge :variant="typeVariant[ligne.type]">{{ typeLabel[ligne.type] }}</Badge>
                </template>
                <template #cellule-parent="{ ligne }">
                    <span v-if="nomParent(ligne)" :title="nomParent(ligne)">{{ nomParent(ligne) }}</span>
                    <span v-else class="text-neutral-400">—</span>
                </template>

                <template #actions="{ ligne, mode }">
                    <button
                        v-if="mode === 'tableau'"
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                        :aria-label="`Modifier ${ligne.nom}`"
                        :title="`Modifier ${ligne.nom}`"
                        @click="editer(ligne)"
                    >
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                    </button>
                    <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier ${ligne.nom}`" @click="editer(ligne)">
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                        Modifier
                    </SecondaryButton>
                    <ActionsMenu v-if="nombreEnfants(ligne) === 0" :libelle="`Autres actions pour ${ligne.nom}`" :actions="actions" @choisir="() => supprimer(ligne)" />
                    <button
                        v-else
                        type="button"
                        aria-disabled="true"
                        class="inline-flex h-11 w-11 cursor-not-allowed items-center justify-center rounded-lg text-neutral-400 ring-1 ring-neutral-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-500 dark:ring-neutral-600"
                        :aria-label="`Supprimer ${ligne.nom} : impossible. ${RAISON_ENFANTS}`"
                        :title="RAISON_ENFANTS"
                    >
                        <Trash2 class="h-4 w-4" aria-hidden="true" />
                    </button>
                </template>
            </DataTable>
        </div>

        <!-- ===== Modification d'une unité ===== -->
        <Modal :show="enEdition !== null" max-width="lg" labelledby="titre-edition-pieu" @close="fermerEdition">
            <form v-if="enEdition" class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-pieu" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Modifier « {{ enEdition.nom }} »
                </h2>
                <div>
                    <InputLabel for="edition-pieu-nom" value="Nom" />
                    <TextInput id="edition-pieu-nom" v-model="editForm.nom" type="text" :class="classeChamp" required />
                    <InputError class="mt-1" :message="editForm.errors.nom" />
                </div>
                <div>
                    <InputLabel for="edition-pieu-type" value="Type" />
                    <select id="edition-pieu-type" v-model="editForm.type" :class="classeChamp" @change="editForm.parent_id = ''">
                        <option value="mission">Mission</option>
                        <option value="district">District</option>
                        <option value="pieu">Pieu</option>
                    </select>
                    <InputError class="mt-1" :message="editForm.errors.type" />
                </div>
                <div v-if="editForm.type !== 'mission'">
                    <InputLabel for="edition-pieu-parent" :value="editForm.type === 'pieu' ? 'District parent (optionnel)' : 'Mission parente (optionnel)'" />
                    <select id="edition-pieu-parent" v-model="editForm.parent_id" :class="classeChamp">
                        <option value="">Aucun</option>
                        <option v-for="p in parentsPossibles(editForm.type, enEdition.id)" :key="p.id" :value="p.id">{{ p.nom }}</option>
                    </select>
                    <InputError class="mt-1" :message="editForm.errors.parent_id" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="editForm.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
