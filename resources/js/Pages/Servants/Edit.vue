<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import UnitePicker from '@/Components/UnitePicker.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { useRoleTheme } from '@/composables/useRoleTheme';

const props = defineProps({
    servant: Object,
    pieux: Array,
    uniteActuelle: { type: Object, default: null },
    retourRoute: String,
    // Statut modifiable par le seul Conseil du Temple (Recommandé / Nouveau /
    // Ancien), jamais pour un servant relevé ou permutant.
    peutChangerStatut: { type: Boolean, default: false },
    statutsModifiables: { type: Array, default: () => [] },
});

const { confirmer } = useConfirm();
const { isAdmin } = useRoleTheme();

const form = useForm({
    nom: props.servant.nom,
    prenom: props.servant.prenom,
    genre: props.servant.genre ?? '',
    telephone: props.servant.telephone ?? '',
    telephone_appel: props.servant.telephone_appel ?? '',
    pieu_id: props.servant.pieu_id ?? '',
    date_appel: props.servant.date_appel ?? '',
    date_debut: props.servant.date_debut ?? '',
    adresse: props.servant.adresse ?? '',
    statut: props.servant.statut,
    titre_leadership: props.servant.titre_leadership ?? '',
    photo: null,
});

const apercuPhoto = ref(props.servant.a_photo ? route('servants.photo', props.servant.id) : null);

const choisirPhoto = (event) => {
    const fichier = event.target.files[0] ?? null;
    form.photo = fichier;
    apercuPhoto.value = fichier ? URL.createObjectURL(fichier) : null;
};

const submit = async () => {
    if (props.peutChangerStatut && form.statut !== props.servant.statut) {
        const choix = props.statutsModifiables.find((s) => s.value === form.statut);
        if (!(await confirmer(`Passer ${props.servant.prenom} ${props.servant.nom} au statut « ${choix?.label ?? form.statut} » ?`))) return;
    }

    // PUT multipart n'est pas parsé par PHP ($_FILES resterait vide) : on envoie en
    // POST avec _method spoofé, qu'Inertia et Laravel savent traiter comme un PUT.
    // Le statut n'est envoyé que par le Conseil du Temple (ignoré sinon côté serveur).
    form.transform((data) => {
        const { statut, ...reste } = data;

        return props.peutChangerStatut ? { ...data, _method: 'put' } : { ...reste, _method: 'put' };
    }).post(route('servants.update', props.servant.id));
};
</script>

<template>
    <Head :title="`Modifier ${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout
        :breadcrumbs="[
            { label: 'Tableau de bord', href: route('dashboard') },
            ...(retourRoute === 'servants.show' ? [{ label: 'Servant(e)s', href: route('servants.index') }] : []),
            { label: `${servant.prenom} ${servant.nom}`, href: route(retourRoute, servant.id) },
            { label: 'Modifier' },
        ]"
    >
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Modifier {{ servant.prenom }} {{ servant.nom }}
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <Link :href="route(retourRoute, servant.id)" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
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
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
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
                        <InputLabel for="pieu_id" value="Pieu / District / Mission" />
                        <UnitePicker id="pieu_id" v-model="form.pieu_id" :unites="pieux" :unite-actuelle="uniteActuelle" />
                        <InputError class="mt-2" :message="form.errors.pieu_id" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="date_appel" value="Date d'appel" />
                            <TextInput id="date_appel" v-model="form.date_appel" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.date_appel" />
                        </div>
                        <div>
                            <InputLabel for="date_debut" value="Date de début" />
                            <TextInput id="date_debut" v-model="form.date_debut" type="date" class="mt-1 block w-full" />
                            <InputError class="mt-2" :message="form.errors.date_debut" />
                        </div>
                    </div>

                    <div>
                        <InputLabel for="adresse" value="Adresse" />
                        <TextInput id="adresse" v-model="form.adresse" type="text" class="mt-1 block w-full" />
                        <InputError class="mt-2" :message="form.errors.adresse" />
                    </div>

                    <div>
                        <InputLabel for="statut" value="Statut" />
                        <select
                            v-if="peutChangerStatut"
                            id="statut"
                            v-model="form.statut"
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                            required
                        >
                            <option v-for="option in statutsModifiables" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                        <div v-else class="mt-1 flex flex-wrap items-center gap-2">
                            <StatusBadge :statut="servant.statut" domain="servant" />
                            <span class="text-sm text-neutral-600 dark:text-neutral-400">
                                {{ isAdmin || ['suspendu', 'retire'].includes(servant.statut)
                                    ? 'Servant(e) relevé(e) ou permutant : le statut se rétablit par la réintégration (onglet Situation de la fiche).'
                                    : 'Le statut ne peut être modifié que par le Conseil du Temple.' }}
                            </span>
                        </div>
                        <InputError class="mt-2" :message="form.errors.statut" />
                    </div>

                    <div>
                        <InputLabel for="titre_leadership" value="Titre de leadership (optionnel)" />
                        <TextInput id="titre_leadership" v-model="form.titre_leadership" type="text" class="mt-1 block w-full" placeholder="Ex. : Coordonnateur du baptistère" />
                        <InputError class="mt-2" :message="form.errors.titre_leadership" />
                    </div>

                    <div>
                        <InputLabel for="photo" value="Photo" />
                        <div class="mt-1 flex items-center gap-4">
                            <img v-if="apercuPhoto" :src="apercuPhoto" alt="Aperçu" class="h-16 w-16 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700" />
                            <input id="photo" type="file" accept="image/*" class="block w-full text-sm text-neutral-600 dark:text-neutral-400" @change="choisirPhoto" />
                        </div>
                        <InputError class="mt-2" :message="form.errors.photo" />
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
