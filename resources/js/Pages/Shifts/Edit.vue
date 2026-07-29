<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    shift: Object,
});

const jours = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

const form = useForm({
    nom: props.shift.nom,
    jour: props.shift.jour,
    heure_debut: props.shift.heure_debut,
    heure_fin: props.shift.heure_fin,
    statut: props.shift.statut,
});

const submit = () => {
    form.put(route('shifts.update', props.shift.id));
};

const destroy = () => {
    if (!confirm('Confirmer la suppression définitive de ce Shift ?')) return;
    router.delete(route('shifts.destroy', props.shift.id));
};
</script>

<template>
    <Head title="Modifier le Shift" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Modifier le Shift : {{ shift.nom }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="nom" value="Nom du Shift" />
                            <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.nom" />
                        </div>

                        <div>
                            <InputLabel for="jour" value="Jour d'activité" />
                            <select
                                id="jour"
                                v-model="form.jour"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                required
                            >
                                <option v-for="jour in jours" :key="jour" :value="jour">
                                    {{ jour.charAt(0).toUpperCase() + jour.slice(1) }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.jour" />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="heure_debut" value="Heure début" />
                                <TextInput id="heure_debut" v-model="form.heure_debut" type="time" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.heure_debut" />
                            </div>
                            <div>
                                <InputLabel for="heure_fin" value="Heure fin" />
                                <TextInput id="heure_fin" v-model="form.heure_fin" type="time" class="mt-1 block w-full" required />
                                <InputError class="mt-2" :message="form.errors.heure_fin" />
                            </div>
                        </div>

                        <div>
                            <InputLabel for="statut" value="Statut" />
                            <select
                                id="statut"
                                v-model="form.statut"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                required
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                        </div>

                        <div class="flex items-center justify-between">
                            <DangerButton type="button" @click="destroy">
                                Supprimer le Shift
                            </DangerButton>
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
