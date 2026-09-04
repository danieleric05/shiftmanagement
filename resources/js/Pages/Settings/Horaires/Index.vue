<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    horaires: Array,
});

const form = useForm({ nom: '', heure_debut: '06:30', heure_fin: '12:30' });

const ajouter = () => {
    form.post(route('settings.horaires.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const supprimer = async (id) => {
    if (!(await confirmer('Supprimer cet horaire ?', { danger: true }))) return;
    router.delete(route('settings.horaires.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Horaires" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Horaires' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Paramètres — Horaires
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <form @submit.prevent="ajouter" class="grid grid-cols-1 gap-4 rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 sm:grid-cols-4">
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

            <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-700">
                    <li v-if="horaires.length === 0" class="p-6 text-center text-neutral-600 dark:text-neutral-400">
                        Aucun horaire enregistré.
                    </li>
                    <li v-for="horaire in horaires" :key="horaire.id" class="flex items-center justify-between p-4">
                        <span class="text-neutral-900 dark:text-neutral-100">
                            {{ horaire.nom }} — {{ horaire.heure_debut }} à {{ horaire.heure_fin }}
                        </span>
                        <DangerButton @click="supprimer(horaire.id)">Supprimer</DangerButton>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
