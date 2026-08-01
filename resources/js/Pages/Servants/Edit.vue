<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    servant: Object,
    pieux: Array,
});

const form = useForm({
    nom: props.servant.nom,
    prenom: props.servant.prenom,
    genre: props.servant.genre ?? '',
    telephone: props.servant.telephone ?? '',
    telephone_appel: props.servant.telephone_appel ?? '',
    pieu_id: props.servant.pieu_id ?? '',
    date_naissance: props.servant.date_naissance ?? '',
    adresse: props.servant.adresse ?? '',
    statut: props.servant.statut,
});

const submit = () => {
    form.put(route('servants.update', props.servant.id));
};
</script>

<template>
    <Head :title="`Modifier ${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Modifier {{ servant.prenom }} {{ servant.nom }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route('servants.show', servant.id)" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>

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
                        <InputLabel for="pieu_id" value="Pieu" />
                        <select
                            id="pieu_id"
                            v-model="form.pieu_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        >
                            <option value="">Non précisé</option>
                            <option v-for="p in pieux" :key="p.id" :value="p.id">{{ p.nom }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.pieu_id" />
                    </div>

                    <div>
                        <InputLabel for="date_naissance" value="Date de naissance" />
                        <TextInput id="date_naissance" v-model="form.date_naissance" type="date" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.date_naissance" />
                    </div>

                    <div>
                        <InputLabel for="adresse" value="Adresse" />
                        <TextInput id="adresse" v-model="form.adresse" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.adresse" />
                    </div>

                    <div>
                        <InputLabel for="statut" value="Statut" />
                        <select
                            id="statut"
                            v-model="form.statut"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            required
                        >
                            <option value="recommande">Recommandé</option>
                            <option value="en_formation">En formation</option>
                            <option value="actif">Actif</option>
                            <option value="suspendu">Suspendu</option>
                            <option value="retire">Retiré</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.statut" />
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
