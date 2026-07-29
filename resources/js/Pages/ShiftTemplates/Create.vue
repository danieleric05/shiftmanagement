<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    nom: '',
    description: '',
});

const submit = () => {
    form.post(route('shift-templates.store'));
};
</script>

<template>
    <Head title="Créer un modèle de Shift" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Créer un modèle de Shift
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="nom" value="Nom du modèle" />
                            <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" placeholder="Ex: Temple Standard" required />
                            <InputError class="mt-2" :message="form.errors.nom" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Description (optionnel)" />
                            <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Les postes du modèle s'ajoutent depuis la fiche du modèle une fois celui-ci créé.
                        </p>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing">
                                Créer le modèle
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
