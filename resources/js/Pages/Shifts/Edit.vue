<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    shift: Object,
});

const { confirmer } = useConfirm();

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

const destroy = async () => {
    if (!(await confirmer('Confirmer la suppression définitive de ce Shift ?', { danger: true }))) return;
    router.delete(route('shifts.destroy', props.shift.id));
};
</script>

<template>
    <Head title="Modifier le Shift" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Shifts', href: route('shifts.index') }, { label: shift.nom, href: route('shifts.show', shift.id) }, { label: 'Modifier' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Modifier le Shift : {{ shift.nom }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('shifts.show', shift.id)" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
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
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                            required
                        >
                            <option v-for="jour in jours" :key="jour" :value="jour">
                                {{ jour.charAt(0).toUpperCase() + jour.slice(1) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.jour" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
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
    </AuthenticatedLayout>
</template>
