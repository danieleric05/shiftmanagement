<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const sections = [
    { nom: 'Pieux', description: 'Gérer la liste des pieux utilisés dans les fiches Servant.', route: 'settings.pieux.index' },
    { nom: 'Horaires', description: 'Gérer les créneaux horaires réutilisables pour créer un Shift.', route: 'settings.horaires.index' },
    { nom: 'Rôles', description: "Modifier le nom et la description des rôles d'accès.", route: 'settings.roles.index' },
    { nom: "Étapes du parcours", description: "Gérer les étapes du parcours d'intégration des servants.", route: 'settings.workflow-steps.index' },
];
</script>

<template>
    <Head title="Paramètres" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Paramètres
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <Link
                        v-for="section in sections"
                        :key="section.route"
                        :href="route(section.route)"
                        class="block rounded-lg bg-white p-6 shadow-sm hover:shadow-md dark:bg-gray-800"
                    >
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ section.nom }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ section.description }}</p>
                    </Link>
                </div>

                <a
                    :href="route('manuel.download')"
                    class="block rounded-lg bg-white p-6 shadow-sm hover:shadow-md dark:bg-gray-800"
                >
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">📄 Mode d'emploi (PDF)</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Télécharger le guide complet d'utilisation de l'application.
                    </p>
                </a>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
