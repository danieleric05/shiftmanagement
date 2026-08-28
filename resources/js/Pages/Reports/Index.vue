<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SortableHeader from '@/Components/SortableHeader.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useTableSort } from '@/composables/useTableSort';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    servantsParStatut: Object,
    remplissageShifts: Array,
    avancementFormation: Object,
});

const joursDisponibles = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']
    .filter((jour) => props.remplissageShifts.some((s) => s.jour === jour));

const { recherche, resultats: remplissageShiftsCherches } = useTableSearch(() => props.remplissageShifts, ['nom']);

const jourFiltre = ref('');
const remplissageShiftsParColonne = computed(() => jourFiltre.value
    ? remplissageShiftsCherches.value.filter((s) => s.jour === jourFiltre.value)
    : remplissageShiftsCherches.value);

const { sortKey, sortDirection, toggleSort, sorted: remplissageShiftsFiltres } = useTableSort(() => remplissageShiftsParColonne.value);

const statutLabel = {
    recommande: 'Recommandés',
    en_formation: 'En formation',
    actif: 'Actifs',
    suspendu: 'Relevés',
    retire: 'Retirés',
};
</script>

<template>
    <Head title="Rapports" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Rapports
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-lg font-medium text-neutral-900">Servants par statut</h3>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                    <div v-for="(count, statut) in servantsParStatut" :key="statut" class="rounded-lg bg-neutral-50 p-4 text-center">
                        <p class="text-2xl font-bold text-neutral-900">{{ count }}</p>
                        <p class="text-xs uppercase text-neutral-600">{{ statutLabel[statut] }}</p>
                    </div>
                </div>
                <div class="mt-4">
                    <a :href="route('reports.servants.csv')">
                        <SecondaryButton>Exporter en Excel (CSV)</SecondaryButton>
                    </a>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900">Taux de remplissage des Shifts</h3>
                    <a :href="route('reports.shifts.pdf')">
                        <PrimaryButton>Exporter en PDF</PrimaryButton>
                    </a>
                </div>
                <div v-if="remplissageShifts.length > 0" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" />
                    <select v-model="jourFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                        <option value="">Tous les jours</option>
                        <option v-for="jour in joursDisponibles" :key="jour" :value="jour" class="capitalize">{{ jour }}</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <SortableHeader label="Shift" sort-key="nom" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Jour" sort-key="jour" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Postes vacants" sort-key="postes_vacants" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Taux" sort-key="taux_remplissage" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-if="remplissageShifts.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-neutral-600">
                                    Aucun Shift pour le moment.
                                </td>
                            </tr>
                            <tr v-else-if="remplissageShiftsFiltres.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-neutral-600">
                                    Aucun Shift ne correspond à ces critères.
                                </td>
                            </tr>
                            <tr v-for="(shift, index) in remplissageShiftsFiltres" :key="index">
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-900">{{ shift.nom }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm capitalize text-neutral-600">{{ shift.jour }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ shift.postes_vacants }} / {{ shift.postes_total }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm">
                                    <span v-if="shift.taux_remplissage === null" class="text-neutral-400">—</span>
                                    <span v-else :class="shift.taux_remplissage === 100 ? 'text-success-700' : 'text-amber-600'">
                                        {{ shift.taux_remplissage }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-lg font-medium text-neutral-900">Avancement du parcours de formation</h3>
                <p class="text-3xl font-bold text-neutral-900">
                    {{ avancementFormation.taux_avancement !== null ? avancementFormation.taux_avancement + '%' : '—' }}
                </p>
                <p class="text-sm text-neutral-600">
                    {{ avancementFormation.etapes_terminees }} étapes terminées sur {{ avancementFormation.total_etapes }} au total (tous servants confondus).
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
