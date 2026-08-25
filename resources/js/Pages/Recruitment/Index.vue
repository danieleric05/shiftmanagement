<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import StatCard from '@/Components/StatCard.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SortableHeader from '@/Components/SortableHeader.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useTableSort } from '@/composables/useTableSort';
import { Head, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { UserCheck, UserPlus } from '@lucide/vue';

const props = defineProps({
    shifts: Array,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const { recherche, resultats: shiftsCherches } = useTableSearch(() => props.shifts, ['shift_nom']);
const { sortKey, sortDirection, toggleSort, sorted: shiftsFiltres } = useTableSort(() => shiftsCherches.value);

const forms = reactive(
    Object.fromEntries(
        props.shifts.map((s) => [
            s.shift_id,
            useForm({
                nombre_a_recruter: s.nombre_a_recruter,
                echeance: s.echeance ?? '',
                notes: s.notes ?? '',
            }),
        ]),
    ),
);

const notesOuvertes = ref({});

const enregistrer = (shiftId) => {
    forms[shiftId].put(route('recruitment.upsert', shiftId), {
        preserveScroll: true,
    });
};

const progression = (shift) => {
    const cible = forms[shift.shift_id].nombre_a_recruter;
    if (!cible || cible <= 0) return null;
    return Math.min(100, Math.round((shift.candidats_actifs / cible) * 100));
};
</script>

<template>
    <Head title="Recrutement" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900">
                <UserPlus class="h-5 w-5 text-primary" />
                Besoins de recrutement
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Total à recruter" :value="compteurs.total_a_recruter" :icon="UserPlus" tone="primary" />
                <StatCard label="Candidats actifs" :value="compteurs.total_candidats_actifs" :icon="UserCheck" tone="success" />
            </div>

            <div v-if="shifts.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Vous ne gérez aucun Shift pour l'instant — les besoins de recrutement apparaîtront ici dès qu'un Shift vous sera confié.
            </div>

            <template v-else>
                <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" class="mb-4" />

                <div v-if="shiftsFiltres.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                    Aucun Shift ne correspond à ces critères.
                </div>

                <div v-else class="overflow-x-auto rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <table class="min-w-full divide-y divide-neutral-100">
                    <thead>
                        <tr>
                            <SortableHeader label="Shift" sort-key="shift_nom" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Pourvu</th>
                            <SortableHeader label="À recruter" sort-key="nombre_a_recruter" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Échéance</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Notes</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <template v-for="shift in shiftsFiltres" :key="shift.shift_id">
                            <tr class="align-top">
                                <td class="px-4 py-2.5 text-sm">
                                    <div class="font-medium text-neutral-900">{{ shift.shift_nom }}</div>
                                    <div class="text-xs text-neutral-500">
                                        {{ shift.candidats_actifs }} candidat(s) actif(s)
                                        <span v-if="shift.coordinateur">· {{ shift.coordinateur.nom }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-2.5">
                                    <div v-if="progression(shift) !== null" class="w-28">
                                        <div class="flex items-center justify-between text-xs text-neutral-500">
                                            <span class="font-medium" :class="progression(shift) >= 100 ? 'text-success-700' : 'text-neutral-700'">
                                                {{ progression(shift) }}%
                                            </span>
                                        </div>
                                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="progression(shift) >= 100 ? 'bg-success-600' : 'bg-primary-light'"
                                                :style="{ width: `${progression(shift)}%` }"
                                            />
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-neutral-400">—</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    <input
                                        type="number"
                                        min="0"
                                        v-model.number="forms[shift.shift_id].nombre_a_recruter"
                                        class="w-20 rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                                    />
                                    <InputError class="mt-1" :message="forms[shift.shift_id].errors.nombre_a_recruter" />
                                </td>
                                <td class="px-4 py-2.5">
                                    <input
                                        type="date"
                                        v-model="forms[shift.shift_id].echeance"
                                        class="w-40 rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                                    />
                                    <InputError class="mt-1" :message="forms[shift.shift_id].errors.echeance" />
                                </td>
                                <td class="px-4 py-2.5">
                                    <button
                                        v-if="!notesOuvertes[shift.shift_id] && !forms[shift.shift_id].notes"
                                        type="button"
                                        class="text-sm text-neutral-400 hover:text-neutral-600"
                                        @click="notesOuvertes[shift.shift_id] = true"
                                    >
                                        + ajouter
                                    </button>
                                    <input
                                        v-else
                                        type="text"
                                        v-model="forms[shift.shift_id].notes"
                                        placeholder="Notes"
                                        class="w-40 rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                                    />
                                    <InputError class="mt-1" :message="forms[shift.shift_id].errors.notes" />
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <PrimaryButton
                                        v-if="forms[shift.shift_id].isDirty"
                                        :disabled="forms[shift.shift_id].processing"
                                        @click="enregistrer(shift.shift_id)"
                                    >
                                        Enregistrer
                                    </PrimaryButton>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
