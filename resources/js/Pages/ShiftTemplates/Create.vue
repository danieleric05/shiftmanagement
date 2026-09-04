<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de Shift', href: route('shift-templates.index') }, { label: 'Nouveau' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Créer un modèle de Shift
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('shift-templates.index')" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
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

                    <p class="text-sm text-neutral-600 dark:text-neutral-400">
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
    </AuthenticatedLayout>
</template>
