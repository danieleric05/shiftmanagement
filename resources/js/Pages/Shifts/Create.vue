<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    templates: Array,
    horaires: Array,
});

const jours = ['mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];

const form = useForm({
    nom: '',
    jour: 'mardi',
    heure_debut: '07:00',
    heure_fin: '11:00',
    shift_template_id: '',
});

const appliquerHoraire = (horaireId) => {
    const horaire = props.horaires.find((h) => h.id === horaireId);
    if (horaire) {
        form.heure_debut = horaire.heure_debut;
        form.heure_fin = horaire.heure_fin;
    }
};

const submit = () => {
    form.post(route('shifts.store'));
};
</script>

<template>
    <Head title="Créer un Shift" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Créer un Shift
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('shifts.index')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <InputLabel for="nom" value="Nom du Shift" />
                        <TextInput
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            class="mt-1 block w-full"
                            placeholder="Ex: Shift A - Mardi matin"
                            required
                        />
                        <InputError class="mt-2" :message="form.errors.nom" />
                    </div>

                    <div>
                        <InputLabel for="jour" value="Jour d'activité" />
                        <select
                            id="jour"
                            v-model="form.jour"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            required
                        >
                            <option v-for="jour in jours" :key="jour" :value="jour">
                                {{ jour.charAt(0).toUpperCase() + jour.slice(1) }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.jour" />
                    </div>

                    <div v-if="horaires.length > 0">
                        <InputLabel for="horaire_id" value="Préremplir avec un horaire (optionnel)" />
                        <select
                            id="horaire_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            @change="appliquerHoraire(Number($event.target.value))"
                        >
                            <option value="">Saisie manuelle</option>
                            <option v-for="horaire in horaires" :key="horaire.id" :value="horaire.id">
                                {{ horaire.nom }} ({{ horaire.heure_debut }} - {{ horaire.heure_fin }})
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="heure_debut" value="Heure début" />
                            <TextInput
                                id="heure_debut"
                                v-model="form.heure_debut"
                                type="time"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.heure_debut" />
                        </div>
                        <div>
                            <InputLabel for="heure_fin" value="Heure fin" />
                            <TextInput
                                id="heure_fin"
                                v-model="form.heure_fin"
                                type="time"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError class="mt-2" :message="form.errors.heure_fin" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="shift_template_id" value="Modèle de Shift (optionnel)" />
                        <select
                            id="shift_template_id"
                            v-model="form.shift_template_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        >
                            <option value="">Aucun modèle</option>
                            <option v-for="template in templates" :key="template.id" :value="template.id">
                                {{ template.nom }}
                            </option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.shift_template_id" />
                        <p class="mt-2 text-sm text-neutral-600">
                            En choisissant un modèle, tous ses postes sont générés automatiquement pour ce Shift ; il ne reste plus qu'à les pourvoir.
                        </p>
                    </div>

                    <p class="text-sm text-neutral-600">
                        L'affectation du Chef d'équipe et des membres se fait depuis la fiche du Shift une fois celui-ci créé.
                    </p>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Créer le Shift
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
