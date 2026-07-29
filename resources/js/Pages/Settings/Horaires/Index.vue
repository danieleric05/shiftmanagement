<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    horaires: Array,
});

const form = useForm({ nom: '', heure_debut: '07:00', heure_fin: '11:00' });

const ajouter = () => {
    form.post(route('settings.horaires.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const supprimer = (id) => {
    if (!confirm('Supprimer cet horaire ?')) return;
    router.delete(route('settings.horaires.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Horaires" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Paramètres — Horaires
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl space-y-6 sm:px-6 lg:px-8">
                <form @submit.prevent="ajouter" class="grid grid-cols-1 gap-4 rounded-md bg-white p-6 shadow-sm dark:bg-gray-800 sm:grid-cols-4">
                    <div class="sm:col-span-2">
                        <InputLabel for="nom" value="Nom" />
                        <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" placeholder="Ex: Matin" required />
                    </div>
                    <div>
                        <InputLabel for="heure_debut" value="Début" />
                        <TextInput id="heure_debut" v-model="form.heure_debut" type="time" class="mt-1 block w-full" required />
                    </div>
                    <div>
                        <InputLabel for="heure_fin" value="Fin" />
                        <TextInput id="heure_fin" v-model="form.heure_fin" type="time" class="mt-1 block w-full" required />
                    </div>
                    <div class="sm:col-span-4 flex justify-end">
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>

                <div class="bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-if="horaires.length === 0" class="p-6 text-center text-gray-500 dark:text-gray-400">
                            Aucun horaire enregistré.
                        </li>
                        <li v-for="horaire in horaires" :key="horaire.id" class="flex items-center justify-between p-4">
                            <span class="text-gray-900 dark:text-gray-100">
                                {{ horaire.nom }} — {{ horaire.heure_debut }} à {{ horaire.heure_fin }}
                            </span>
                            <DangerButton @click="supprimer(horaire.id)">Supprimer</DangerButton>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
