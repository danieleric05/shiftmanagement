<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="Temple Servant Manager" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-6">
        <div class="flex flex-col items-center">
            <ApplicationLogo class="h-20 w-20 fill-current text-primary" />
            <h1 class="mt-4 text-2xl font-semibold text-neutral-900">
                Temple Servant Manager
            </h1>
            <p class="mt-2 max-w-md text-center text-sm text-neutral-600">
                Gestion des servants, des Shifts et de leurs postes, du parcours d'intégration
                et de la gouvernance du Temple.
            </p>

            <div v-if="canLogin" class="mt-8 flex items-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
                >
                    Accéder au tableau de bord
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-white transition duration-150 ease-in-out hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
                    >
                        Se connecter
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-primary-light bg-white px-4 py-2.5 text-sm font-medium text-primary transition duration-150 ease-in-out hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
                    >
                        S'inscrire
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
