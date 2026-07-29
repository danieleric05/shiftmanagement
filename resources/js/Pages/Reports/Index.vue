<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    servantsParStatut: Object,
    remplissageShifts: Array,
    avancementFormation: Object,
});

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
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Rapports
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Servants par statut</h3>
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-5">
                        <div v-for="(count, statut) in servantsParStatut" :key="statut" class="rounded-lg bg-gray-50 p-4 text-center dark:bg-gray-900">
                            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ count }}</p>
                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400">{{ statutLabel[statut] }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a :href="route('reports.servants.csv')">
                            <SecondaryButton>Exporter en Excel (CSV)</SecondaryButton>
                        </a>
                    </div>
                </div>

                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Taux de remplissage des Shifts</h3>
                        <a :href="route('reports.shifts.pdf')">
                            <PrimaryButton>Exporter en PDF</PrimaryButton>
                        </a>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Shift</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Jour</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Postes vacants</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Taux</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="remplissageShifts.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Aucun Shift pour le moment.
                                </td>
                            </tr>
                            <tr v-for="(shift, index) in remplissageShifts" :key="index">
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ shift.nom }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm capitalize text-gray-500 dark:text-gray-400">{{ shift.jour }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ shift.postes_vacants }} / {{ shift.postes_total }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm">
                                    <span v-if="shift.taux_remplissage === null" class="text-gray-400 dark:text-gray-500">—</span>
                                    <span v-else :class="shift.taux_remplissage === 100 ? 'text-green-700 dark:text-green-400' : 'text-amber-600 dark:text-amber-400'">
                                        {{ shift.taux_remplissage }}%
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Avancement du parcours de formation</h3>
                    <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">
                        {{ avancementFormation.taux_avancement !== null ? avancementFormation.taux_avancement + '%' : '—' }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ avancementFormation.etapes_terminees }} étapes terminées sur {{ avancementFormation.total_etapes }} au total (tous servants confondus).
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
