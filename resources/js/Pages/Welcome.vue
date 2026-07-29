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

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-6 dark:bg-gray-900">
        <div class="flex flex-col items-center">
            <ApplicationLogo class="h-20 w-20 fill-current text-indigo-500" />
            <h1 class="mt-4 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                Temple Servant Manager
            </h1>
            <p class="mt-2 max-w-md text-center text-sm text-gray-500 dark:text-gray-400">
                Gestion des servants, des Shifts et de leurs postes, du parcours d'intégration
                et de la gouvernance du Temple.
            </p>

            <div v-if="canLogin" class="mt-8 flex items-center gap-4">
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                >
                    Accéder au tableau de bord
                </Link>

                <template v-else>
                    <Link
                        :href="route('login')"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Se connecter
                    </Link>

                    <Link
                        v-if="canRegister"
                        :href="route('register')"
                        class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 ring-1 ring-gray-300 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:ring-gray-700 dark:hover:text-gray-100"
                    >
                        S'inscrire
                    </Link>
                </template>
            </div>
        </div>
    </div>
</template>
