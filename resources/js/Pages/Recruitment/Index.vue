<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Pencil, UserPlus } from '@lucide/vue';

const props = defineProps({
    shifts: Array,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const { recherche, resultats: shiftsCherches } = useTableSearch(() => props.shifts, ['shift_nom']);

// Rôle « Autres » : besoins affichés en lecture seule, sans modification possible.
const lectureSeule = computed(() => Boolean(usePage().props.auth.lectureSeule));

// Tri client (liste complète en mémoire) sur chaque colonne.
const colonnes = [
    { cle: 'shift_nom', libelle: 'Shift', triable: true, principale: true, priorite: 1, largeurMin: 200, tronquer: false },
    { cle: 'nombre_a_recruter', libelle: 'À recruter', triable: true, priorite: 1, largeurMin: 110 },
    { cle: 'echeance', libelle: 'Échéance', triable: true, priorite: 2, largeur: '8rem', largeurMin: 128 },
    { cle: 'notes', libelle: 'Notes', triable: true, priorite: 3, largeurMin: 200 },
];

// ---- Modification d'un besoin (fenêtre modale, plus d'édition en ligne) ----
const idEnEdition = ref(null);
const enEdition = computed(() => props.shifts.find((s) => s.shift_id === idEnEdition.value) ?? null);

const form = useForm({
    nombre_a_recruter: 0,
    echeance: '',
    notes: '',
});

const editer = (shift) => {
    form.defaults({
        nombre_a_recruter: shift.nombre_a_recruter,
        echeance: shift.echeance ?? '',
        notes: shift.notes ?? '',
    });
    form.reset();
    form.clearErrors();
    idEnEdition.value = shift.shift_id;
};

const fermer = () => (idEnEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    form.put(route('recruitment.upsert', enEdition.value.shift_id), {
        preserveScroll: true,
        onSuccess: fermer,
    });
};

const classeChamp = 'mt-1 block w-full min-h-[44px] rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500';
</script>

<template>
    <Head title="Recrutement" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Recrutement' }]">
        <template #header>
            <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                <UserPlus class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                Besoins de recrutement
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:w-1/2">
                <StatCard label="Total à recruter" :value="compteurs.total_a_recruter" :icon="UserPlus" tone="primary" />
            </div>

            <div v-if="shifts.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <template v-if="lectureSeule">Aucun Shift dans l'organisation pour l'instant.</template>
                <template v-else>Vous ne gérez aucun Shift pour l'instant — les besoins de recrutement apparaîtront ici dès qu'un Shift vous sera confié.</template>
            </div>

            <template v-else>
                <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" label="Rechercher un Shift" />

                <DataTable
                    :colonnes="colonnes"
                    :lignes="shiftsCherches"
                    cle-ligne="shift_id"
                    legende="Besoins de recrutement par Shift"
                    :filtre-actif="Boolean(recherche)"
                    :compteur="`${shiftsCherches.length} Shift${shiftsCherches.length > 1 ? 's' : ''}`"
                    message-aucun-resultat="Aucun Shift ne correspond à ces critères."
                >
                    <template #cellule-shift_nom="{ ligne }">
                        <span class="[overflow-wrap:anywhere]">{{ ligne.shift_nom }}</span>
                        <span v-if="ligne.coordinateur" class="block truncate text-xs font-normal text-neutral-500 dark:text-neutral-400" :title="ligne.coordinateur.nom">
                            {{ ligne.coordinateur.nom }}
                        </span>
                    </template>
                    <template #cellule-notes="{ ligne, mode }">
                        <span v-if="ligne.notes" :class="mode === 'tableau' ? '' : 'line-clamp-2'" :title="ligne.notes">{{ ligne.notes }}</span>
                        <span v-else class="text-neutral-400">—</span>
                    </template>

                    <template v-if="!lectureSeule" #actions="{ ligne, mode }">
                        <button
                            v-if="mode === 'tableau'"
                            type="button"
                            class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                            :aria-label="`Modifier le besoin de recrutement de ${ligne.shift_nom}`"
                            :title="`Modifier le besoin de recrutement de ${ligne.shift_nom}`"
                            @click="editer(ligne)"
                        >
                            <Pencil class="h-4 w-4" aria-hidden="true" />
                        </button>
                        <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier le besoin de recrutement de ${ligne.shift_nom}`" @click="editer(ligne)">
                            <Pencil class="h-4 w-4" aria-hidden="true" />
                            Modifier
                        </SecondaryButton>
                    </template>
                </DataTable>
            </template>
        </div>

        <Modal v-if="!lectureSeule" :show="enEdition !== null" max-width="lg" labelledby="titre-besoin-recrutement" @close="fermer">
            <form v-if="enEdition" class="p-6" @submit.prevent="enregistrer">
                <h2 id="titre-besoin-recrutement" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Besoin de recrutement — {{ enEdition.shift_nom }}
                </h2>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="besoin-nombre" value="À recruter" />
                        <input id="besoin-nombre" v-model.number="form.nombre_a_recruter" type="number" min="0" required :class="classeChamp" />
                        <InputError class="mt-2" :message="form.errors.nombre_a_recruter" />
                    </div>
                    <div>
                        <InputLabel for="besoin-echeance" value="Échéance" />
                        <input id="besoin-echeance" v-model="form.echeance" type="date" :class="classeChamp" />
                        <InputError class="mt-2" :message="form.errors.echeance" />
                    </div>
                    <div class="sm:col-span-2">
                        <InputLabel for="besoin-notes" value="Notes" />
                        <textarea id="besoin-notes" v-model="form.notes" rows="3" :class="classeChamp"></textarea>
                        <InputError class="mt-2" :message="form.errors.notes" />
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="fermer">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="form.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
