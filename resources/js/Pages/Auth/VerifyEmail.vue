<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Vérification de l'email" />

        <div class="mb-4 text-sm text-neutral-600 dark:text-neutral-400">
            Merci de votre inscription ! Avant de commencer, pourriez-vous
            vérifier votre adresse email en cliquant sur le lien que nous
            venons de vous envoyer ? Si vous ne l'avez pas reçu, nous vous en
            renverrons un avec plaisir.
        </div>

        <div
            class="mb-4 text-sm font-medium text-success-700 dark:text-success-400"
            v-if="verificationLinkSent"
        >
            Un nouveau lien de vérification a été envoyé à l'adresse email
            fournie lors de l'inscription.
        </div>

        <form @submit.prevent="submit">
            <div class="mt-4 flex items-center justify-between">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Renvoyer l'email de vérification
                </PrimaryButton>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="rounded-md text-sm font-medium text-primary-light underline hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
                    >Se déconnecter</Link
                >
            </div>
        </form>
    </GuestLayout>
</template>
