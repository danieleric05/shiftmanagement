<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';

const props = defineProps({
    // Fourni par SettingsController : Conseil du Temple / Super Administrateur d'une organisation.
    afficherLicence: { type: Boolean, default: false },
});

// La gestion des rôles (configuration technique) est réservée au Super Administrateur.
const estSuperAdmin = usePage().props.auth.role === 'super_admin';

const sections = [
    { nom: 'Pieux', description: 'Gérer la liste des pieux utilisés dans les fiches Servant(e).', route: 'settings.pieux.index' },
    { nom: 'Horaires', description: 'Gérer les créneaux horaires réutilisables pour créer un Shift.', route: 'settings.horaires.index' },
    ...(estSuperAdmin ? [{ nom: 'Rôles', description: "Modifier le nom et la description des rôles d'accès.", route: 'settings.roles.index' }] : []),
    { nom: 'Utilisateurs', description: 'Voir qui détient quel rôle, créer un compte, changer un rôle ou suspendre un accès.', route: 'settings.users.index' },
    { nom: "Étapes du parcours", description: "Gérer les étapes du parcours d'intégration des servant(e)s.", route: 'settings.workflow-steps.index' },
    { nom: "Journal d'activité", description: "Consulter l'historique des créations, modifications et suppressions.", route: 'settings.activity-log.index' },
    ...(props.afficherLicence ? [{ nom: 'Licence', description: "Consulter l'état et la date d'expiration de la licence de l'organisation.", route: 'settings.licence.index', icone: KeyRound }] : []),
];
</script>

<template>
    <Head title="Paramètres" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Paramètres
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <Link
                    v-for="section in sections"
                    :key="section.route"
                    :href="route(section.route)"
                    class="block rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 hover:shadow-md"
                >
                    <h3 class="flex items-center gap-2 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                        <component :is="section.icone" v-if="section.icone" class="h-5 w-5 text-primary dark:text-primary-light" aria-hidden="true" />
                        {{ section.nom }}
                    </h3>
                    <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ section.description }}</p>
                </Link>
            </div>

            <a
                :href="route('manuel.download')"
                class="block rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 hover:shadow-md"
            >
                <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">📄 Mode d'emploi (PDF)</h3>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Télécharger le guide complet d'utilisation de l'application.
                </p>
            </a>
        </div>
    </AuthenticatedLayout>
</template>
