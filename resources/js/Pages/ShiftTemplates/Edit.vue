<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

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

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de Shift', href: route('shift-templates.index') }, { label: template.nom, href: route('shift-templates.show', template.id) }, { label: 'Modifier' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Modifier {{ template.nom }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('shift-templates.show', template.id)" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
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
    </AuthenticatedLayout>
</template>
