<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const sections = [
    { nom: 'Pieux', description: 'Gérer la liste des pieux utilisés dans les fiches Servant.', route: 'settings.pieux.index' },
    { nom: 'Horaires', description: 'Gérer les créneaux horaires réutilisables pour créer un Shift.', route: 'settings.horaires.index' },
    { nom: 'Rôles', description: "Modifier le nom et la description des rôles d'accès.", route: 'settings.roles.index' },
    { nom: 'Utilisateurs', description: 'Voir qui détient quel rôle, créer un compte, changer un rôle ou suspendre un accès.', route: 'settings.users.index' },
    { nom: "Étapes du parcours", description: "Gérer les étapes du parcours d'intégration des servants.", route: 'settings.workflow-steps.index' },
    { nom: "Journal d'activité", description: "Consulter l'historique des créations, modifications et suppressions.", route: 'settings.activity-log.index' },
];
</script>

<template>
    <Head title="Paramètres" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Paramètres
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Link
                    v-for="section in sections"
                    :key="section.route"
                    :href="route(section.route)"
                    class="block rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 hover:shadow-md"
                >
                    <h3 class="text-lg font-medium text-neutral-900">{{ section.nom }}</h3>
                    <p class="mt-1 text-sm text-neutral-600">{{ section.description }}</p>
                </Link>
            </div>

            <a
                :href="route('manuel.download')"
                class="block rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 hover:shadow-md"
            >
                <h3 class="text-lg font-medium text-neutral-900">📄 Mode d'emploi (PDF)</h3>
                <p class="mt-1 text-sm text-neutral-600">
                    Télécharger le guide complet d'utilisation de l'application.
                </p>
            </a>
        </div>
    </AuthenticatedLayout>
</template>
