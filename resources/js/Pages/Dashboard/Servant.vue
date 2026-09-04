<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarClock, Clock, MapPin } from '@lucide/vue';

defineProps({
    servant: Object,
    affectations: Array,
});

const page = usePage();
</script>

<template>
    <Head title="Mon espace" />

    <AuthenticatedLayout>
        <template #header>Bonjour, {{ page.props.auth.user.name }} 👋</template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div v-if="!servant" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Votre compte n'est associé à aucune fiche Servant pour le moment.
                Contactez un administrateur pour lier votre compte.
            </div>

            <template v-else>
                <div class="flex items-center justify-between rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Mon profil</p>
                        <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">{{ servant.nom_complet }}</p>
                    </div>
                    <StatusBadge :statut="servant.statut" domain="servant" />
                </div>

                <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Mes affectations</h3>
                        <span class="rounded-full bg-servant-50 px-2.5 py-0.5 text-xs font-medium text-servant">
                            {{ affectations.length }} en cours
                        </span>
                    </div>

                    <div v-if="affectations.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
                        Vous n'êtes actuellement affecté à aucun poste.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div
                            v-for="affectation in affectations"
                            :key="affectation.id"
                            class="rounded-lg border border-neutral-100 dark:border-neutral-700 bg-neutral-50 dark:bg-neutral-900 p-4"
                        >
                            <p class="flex items-center gap-2 font-semibold capitalize text-neutral-900 dark:text-neutral-100">
                                <CalendarClock class="h-4 w-4 text-servant" />
                                {{ affectation.jour }} — {{ affectation.shift }}
                            </p>
                            <p class="mt-1.5 flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                                <Clock class="h-4 w-4" />
                                {{ affectation.heure_debut }} - {{ affectation.heure_fin }}
                            </p>
                            <p class="mt-1 flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                                <MapPin class="h-4 w-4" />
                                Poste : {{ affectation.poste }}
                            </p>
                            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                                Depuis le {{ affectation.depuis }}
                            </p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
