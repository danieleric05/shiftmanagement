<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    pieux: Array,
});

const form = useForm({ nom: '' });

const ajouter = () => {
    form.post(route('settings.pieux.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForm = useForm({ nom: '' });

const editer = (pieu) => {
    enEdition.value = pieu.id;
    editForm.nom = pieu.nom;
};

const enregistrer = (id) => {
    editForm.put(route('settings.pieux.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = (id) => {
    if (!confirm('Supprimer ce pieu ?')) return;
    router.delete(route('settings.pieux.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Pieux" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Paramètres — Pieux
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="ajouter" class="flex gap-3 rounded-md bg-white p-6 shadow-sm dark:bg-gray-800">
                    <TextInput v-model="form.nom" type="text" class="block w-full" placeholder="Nom du pieu" required />
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </form>

                <div class="bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-if="pieux.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            Aucun pieu enregistré.
                        </li>
                        <li v-for="pieu in pieux" :key="pieu.id" class="flex items-center justify-between p-4">
                            <span v-if="enEdition !== pieu.id" class="text-gray-900 dark:text-gray-100">{{ pieu.nom }}</span>
                            <input
                                v-else
                                v-model="editForm.nom"
                                type="text"
                                class="mr-3 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            />
                            <div class="flex items-center gap-2">
                                <template v-if="enEdition === pieu.id">
                                    <PrimaryButton @click="enregistrer(pieu.id)">Enregistrer</PrimaryButton>
                                    <button type="button" class="text-sm text-gray-500 dark:text-gray-400" @click="enEdition = null">Annuler</button>
                                </template>
                                <template v-else>
                                    <button type="button" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400" @click="editer(pieu)">Modifier</button>
                                    <DangerButton @click="supprimer(pieu.id)">Supprimer</DangerButton>
                                </template>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
