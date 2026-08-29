<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UnitePicker from '@/Components/UnitePicker.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    pieux: Array,
});

const form = useForm({
    nom: '',
    prenom: '',
    genre: '',
    telephone: '',
    telephone_appel: '',
    pieu_id: '',
    date_naissance: '',
    adresse: '',
    titre_leadership: '',
    photo: null,
});

const apercuPhoto = ref(null);

const choisirPhoto = (event) => {
    const fichier = event.target.files[0] ?? null;
    form.photo = fichier;
    apercuPhoto.value = fichier ? URL.createObjectURL(fichier) : null;
};

const submit = () => {
    form.post(route('servants.store'));
};
</script>

<template>
    <Head title="Ajouter un Servant" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Servants', href: route('servants.index') }, { label: 'Nouveau' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Ajouter un Servant
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('servants.index')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="prenom" value="Prénom" />
                            <TextInput id="prenom" v-model="form.prenom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.prenom" />
                        </div>
                        <div>
                            <InputLabel for="nom" value="Nom" />
                            <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-2" :message="form.errors.nom" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="genre" value="Genre" />
                        <select
                            id="genre"
                            v-model="form.genre"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        >
                            <option value="">Non précisé</option>
                            <option value="homme">Homme</option>
                            <option value="femme">Femme</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.genre" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="telephone" value="Téléphone" />
                            <TextInput id="telephone" v-model="form.telephone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.telephone" />
                        </div>
                        <div>
                            <InputLabel for="telephone_appel" value="Téléphone (appel)" />
                            <TextInput id="telephone_appel" v-model="form.telephone_appel" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.telephone_appel" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Pieu / District / Mission" />
                        <UnitePicker v-model="form.pieu_id" :unites="pieux" />
                        <InputError class="mt-2" :message="form.errors.pieu_id" />
                        <p v-if="pieux.length === 0" class="mt-1 text-xs text-neutral-600">
                            Aucune unité enregistrée — ajoutez-en depuis Paramètres → Pieux.
                        </p>
                    </div>

                    <div>
                        <InputLabel for="date_naissance" value="Date de naissance (optionnel)" />
                        <TextInput id="date_naissance" v-model="form.date_naissance" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.date_naissance" />
                    </div>

                    <div>
                        <InputLabel for="adresse" value="Adresse (optionnel)" />
                        <TextInput id="adresse" v-model="form.adresse" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.adresse" />
                    </div>

                    <div>
                        <InputLabel for="titre_leadership" value="Titre de leadership (optionnel)" />
                        <TextInput id="titre_leadership" v-model="form.titre_leadership" type="text" class="mt-1 block w-full" placeholder="Ex. : Coordonnateur du baptistère" />
                        <InputError class="mt-2" :message="form.errors.titre_leadership" />
                    </div>

                    <div>
                        <InputLabel for="photo" value="Photo (optionnel)" />
                        <div class="mt-1 flex items-center gap-4">
                            <img v-if="apercuPhoto" :src="apercuPhoto" alt="Aperçu" class="h-16 w-16 rounded-full object-cover ring-1 ring-neutral-200" />
                            <input id="photo" type="file" accept="image/*" class="block w-full text-sm text-neutral-600" @change="choisirPhoto" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.photo" />
                    </div>

                    <p class="text-sm text-neutral-600">
                        Le servant est créé avec le statut « Recommandé » et son parcours d'intégration démarre automatiquement.
                    </p>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">
                            Créer le Servant
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
