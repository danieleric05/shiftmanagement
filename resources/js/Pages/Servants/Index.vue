<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    servants: Array,
    compteurs: Object,
});

const statutLabel = {
    recommande: 'Recommandé',
    en_formation: 'En formation',
    actif: 'Actif',
    suspendu: 'Suspendu',
    retire: 'Retiré',
};

const statutClass = {
    recommande: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    en_formation: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    actif: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    suspendu: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    retire: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
};
</script>

<template>
    <Head title="Gestion des Servants" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Gestion des Servants
                </h2>
                <Link :href="route('servants.create')">
                    <PrimaryButton>+ Ajouter un Servant</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="bg-white p-4 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Actifs</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ compteurs.actifs }}</div>
                    </div>
                    <div class="bg-white p-4 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">En formation</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ compteurs.en_formation }}</div>
                    </div>
                    <div class="bg-white p-4 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Recommandés</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ compteurs.recommandes }}</div>
                    </div>
                    <div class="bg-white p-4 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div class="text-xs uppercase text-gray-500 dark:text-gray-400">Suspendus</div>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ compteurs.suspendus }}</div>
                    </div>
                </div>

                <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Téléphone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Pieu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Statut</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr v-if="servants.length === 0">
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                    Aucun servant pour le moment.
                                </td>
                            </tr>
                            <tr v-for="servant in servants" :key="servant.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ servant.prenom }} {{ servant.nom }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ servant.telephone ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                    {{ servant.pieu ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statutClass[servant.statut]">
                                        {{ statutLabel[servant.statut] }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('servants.show', servant.id)" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
