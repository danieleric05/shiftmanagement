<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    status: Number,
});

const page = usePage();

const messages = {
    403: {
        title: 'Accès refusé',
        description: "Vous n'avez pas la permission d'accéder à cette page.",
    },
    404: {
        title: 'Page introuvable',
        description: "La page que vous cherchez n'existe pas ou a été déplacée.",
    },
    419: {
        title: 'Session expirée',
        description: 'Merci de réessayer.',
    },
    429: {
        title: 'Trop de requêtes',
        description: 'Merci de patienter quelques instants avant de réessayer.',
    },
    500: {
        title: 'Erreur serveur',
        description: 'Une erreur inattendue est survenue. Réessaie dans quelques instants.',
    },
    503: {
        title: 'Maintenance en cours',
        description: "L'application est temporairement indisponible.",
    },
};

const message = computed(() => messages[props.status] ?? {
    title: 'Erreur',
    description: 'Une erreur est survenue.',
});

const retourHref = computed(() => (page.props.auth?.user ? '/dashboard' : '/'));
</script>

<template>
    <Head :title="`Erreur ${status}`" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-6">
        <div class="flex flex-col items-center text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary">
                <UsersRound class="h-7 w-7 text-white" stroke-width="2.25" />
            </div>

            <p class="mt-6 text-6xl font-bold text-primary">{{ status }}</p>
            <h1 class="mt-3 text-xl font-semibold text-neutral-900">{{ message.title }}</h1>
            <p class="mt-2 max-w-md text-sm text-neutral-600">{{ message.description }}</p>

            <Link
                :href="retourHref"
                class="mt-8 inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
            >
                Retour à l'accueil
            </Link>
        </div>
    </div>
</template>
