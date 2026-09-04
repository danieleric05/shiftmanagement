<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import ParcoursIntegration from '@/Components/ParcoursIntegration.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    servant: Object,
    etapes: Array,
    etapesDisponibles: Array,
});

const demarrerParcoursForm = useForm({});
const demarrerParcours = () => {
    demarrerParcoursForm.post(route('servants.workflow.demarrer', props.servant.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: `${servant.prenom} ${servant.nom}` }]">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img
                        v-if="servant.a_photo"
                        :src="route('servants.photo', servant.id)"
                        alt="Photo"
                        class="h-10 w-10 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700"
                    />
                    <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                        {{ servant.prenom }} {{ servant.nom }}
                    </h2>
                </div>
                <div class="flex items-center gap-4">
                    <Link :href="route('servants.edit', servant.id)" class="text-sm font-medium text-primary-light hover:text-primary dark:hover:text-primary-300">
                        Modifier
                    </Link>
                    <Link :href="route('dashboard')" class="text-sm text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100">← Retour</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Téléphone</p>
                        <p class="text-neutral-900 dark:text-neutral-100">{{ servant.telephone ?? '—' }}</p>
                    </div>
                    <div v-if="servant.titre_leadership" class="text-right">
                        <p class="text-sm text-neutral-600 dark:text-neutral-400">Titre de leadership</p>
                        <p class="text-neutral-900 dark:text-neutral-100">{{ servant.titre_leadership }}</p>
                    </div>
                    <StatusBadge :statut="servant.statut" domain="servant" />
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <h3 class="mb-4 text-base font-semibold text-neutral-900 dark:text-neutral-100">Parcours d'intégration</h3>

                <div v-if="etapes.length === 0" class="flex items-center gap-3">
                    <span class="text-sm text-neutral-600 dark:text-neutral-400">Aucun parcours démarré pour ce servant.</span>
                    <PrimaryButton :disabled="demarrerParcoursForm.processing" @click="demarrerParcours">
                        Démarrer le parcours
                    </PrimaryButton>
                </div>
                <ParcoursIntegration v-else :servant-id="servant.id" :etapes="etapes" :etapes-disponibles="etapesDisponibles" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
