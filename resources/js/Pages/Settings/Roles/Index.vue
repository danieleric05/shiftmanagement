<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: Array,
});

const enEdition = ref(null);
const form = useForm({ nom: '', description: '' });

const editer = (role) => {
    enEdition.value = role.id;
    form.nom = role.nom;
    form.description = role.description ?? '';
};

const enregistrer = (id) => {
    form.put(route('settings.roles.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};
</script>

<template>
    <Head title="Rôles" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Paramètres — Rôles
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div v-for="role in roles" :key="role.id" class="rounded-md bg-white p-6 shadow-sm dark:bg-gray-800">
                    <div v-if="enEdition !== role.id" class="flex items-center justify-between">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ role.nom }}</div>
                            <div class="text-xs uppercase text-gray-400 dark:text-gray-500">{{ role.slug }}</div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ role.description }}</p>
                        </div>
                        <button type="button" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400" @click="editer(role)">
                            Modifier
                        </button>
                    </div>
                    <form v-else @submit.prevent="enregistrer(role.id)" class="space-y-3">
                        <input
                            v-model="form.nom"
                            type="text"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        />
                        <textarea
                            v-model="form.description"
                            rows="2"
                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        ></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" class="text-sm text-gray-500 dark:text-gray-400" @click="enEdition = null">Annuler</button>
                            <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
