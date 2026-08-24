<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    servantsParStatut: Object,
    remplissageShifts: Array,
    avancementFormation: Object,
});

const { recherche, resultats: remplissageShiftsFiltres } = useTableSearch(() => props.remplissageShifts, ['nom']);

const statutLabel = {
    recommande: 'Recommandés',
    en_formation: 'En formation',
    actif: 'Actifs',
    suspendu: 'Suspendus',
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
                <SearchInput v-if="remplissageShifts.length > 0" v-model="recherche" placeholder="Rechercher un Shift…" class="mb-4" />

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Shift</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Jour</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Postes vacants</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Taux</th>
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
                                    Aucun Shift ne correspond à « {{ recherche }} ».
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
