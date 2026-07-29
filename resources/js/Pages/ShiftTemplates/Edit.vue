<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    template: Object,
});

const form = useForm({
    nom: props.template.nom,
    description: props.template.description ?? '',
});

const submit = () => {
    form.put(route('shift-templates.update', props.template.id));
};
</script>

<template>
    <Head :title="`Modifier ${template.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Modifier {{ template.nom }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="nom" value="Nom du modèle" />
                            <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.nom" />
                        </div>

                        <div>
                            <InputLabel for="description" value="Description" />
                            <TextInput id="description" v-model="form.description" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.description" />
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="form.processing">
                                Enregistrer
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
