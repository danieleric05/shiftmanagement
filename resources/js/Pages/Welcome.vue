<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
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
    <Head title="Temple Shift Management" />

    <div class="relative flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-6 dark:bg-neutral-900">
        <ThemeToggle class="absolute right-4 top-4" />

        <div class="flex flex-col items-center">
            <ApplicationLogo class="h-20 w-20 fill-current text-primary" />
            <h1 class="mt-4 text-2xl font-semibold text-neutral-900 dark:text-neutral-100">
                Temple Shift Management
            </h1>
            <p class="mt-2 max-w-md text-center text-sm text-neutral-600 dark:text-neutral-400">
                Gestion des shifts
            </p>

            <div v-if="canLogin" class="mt-8 flex items-center gap-4">
                <Link v-if="$page.props.auth.user" :href="route('dashboard')">
                    <PrimaryButton>Accéder au tableau de bord</PrimaryButton>
                </Link>

                <template v-else>
                    <Link :href="route('login')">
                        <PrimaryButton>Se connecter</PrimaryButton>
                    </Link>

                    <Link v-if="canRegister" :href="route('register')">
                        <SecondaryButton>S'inscrire</SecondaryButton>
                    </Link>
                </template>
            </div>
        </div>

        <div class="absolute inset-x-0 bottom-6 flex justify-center gap-4 text-xs text-neutral-500">
            <Link href="/cgu" class="hover:text-neutral-700 dark:hover:text-neutral-300">Conditions générales d'utilisation</Link>
            <span>·</span>
            <Link href="/confidentialite" class="hover:text-neutral-700 dark:hover:text-neutral-300">Politique de confidentialité</Link>
        </div>
    </div>
</template>
