<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    etapes: Array,
});

const form = useForm({ cle: '', nom: '' });

const ajouter = () => {
    form.post(route('settings.workflow-steps.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForm = useForm({ nom: '', ordre: 1 });

const editer = (etape) => {
    enEdition.value = etape.id;
    editForm.nom = etape.nom;
    editForm.ordre = etape.ordre;
};

const enregistrer = (id) => {
    editForm.put(route('settings.workflow-steps.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = (id) => {
    if (!confirm("Supprimer cette étape ? Elle sera retirée du parcours de tous les servants.")) return;
    router.delete(route('settings.workflow-steps.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Étapes du parcours" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Paramètres — Étapes du parcours d'intégration
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="ajouter" class="grid grid-cols-1 gap-4 rounded-md bg-white p-6 shadow-sm dark:bg-gray-800 sm:grid-cols-3">
                    <div>
                        <InputLabel for="cle" value="Clé (technique)" />
                        <TextInput id="cle" v-model="form.cle" type="text" class="mt-1 block w-full" placeholder="ex: entretien_final" required />
                    </div>
                    <div>
                        <InputLabel for="nom" value="Nom affiché" />
                        <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" placeholder="Ex: Entretien final" required />
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>

                <div class="bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-if="etapes.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            Aucune étape définie.
                        </li>
                        <li v-for="etape in etapes" :key="etape.id" class="p-4">
                            <div v-if="enEdition !== etape.id" class="flex items-center justify-between">
                                <span class="text-gray-900 dark:text-gray-100">{{ etape.ordre }}. {{ etape.nom }}</span>
                                <div class="flex items-center gap-3">
                                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400" @click="editer(etape)">
                                        Modifier
                                    </button>
                                    <DangerButton @click="supprimer(etape.id)">Supprimer</DangerButton>
                                </div>
                            </div>
                            <form v-else @submit.prevent="enregistrer(etape.id)" class="flex items-center gap-3">
                                <input
                                    v-model="editForm.nom"
                                    type="text"
                                    class="block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                />
                                <input
                                    v-model.number="editForm.ordre"
                                    type="number"
                                    min="1"
                                    class="block w-24 rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                />
                                <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
                                <button type="button" class="text-sm text-gray-500 dark:text-gray-400" @click="enEdition = null">Annuler</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
