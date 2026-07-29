<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    template: Object,
    positions: Array,
});

const form = useForm({
    nom: '',
});

const ajouterPoste = () => {
    form.post(route('shift-templates.positions.store', props.template.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const supprimerPoste = (positionId) => {
    if (!confirm('Supprimer ce poste du modèle ?')) return;
    router.delete(route('shift-templates.positions.destroy', [props.template.id, positionId]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="template.nom" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ template.nom }}
                </h2>
                <Link :href="route('shift-templates.edit', template.id)" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
                <div v-if="template.description" class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <p class="text-gray-700 dark:text-gray-300">{{ template.description }}</p>
                </div>

                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <h3 class="mb-4 text-lg font-medium text-gray-900 dark:text-gray-100">Postes du modèle</h3>

                    <form @submit.prevent="ajouterPoste" class="mb-6 flex gap-3">
                        <TextInput
                            v-model="form.nom"
                            type="text"
                            class="block w-full"
                            placeholder="Ex: Coordinateur Adjoint"
                            required
                        />
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </form>

                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-if="positions.length === 0" class="py-6 text-center text-gray-500 dark:text-gray-400">
                            Aucun poste défini pour ce modèle.
                        </li>
                        <li v-for="position in positions" :key="position.id" class="flex items-center justify-between py-3">
                            <span class="text-gray-900 dark:text-gray-100">{{ position.ordre }}. {{ position.nom }}</span>
                            <DangerButton @click="supprimerPoste(position.id)">Retirer</DangerButton>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
