<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    servant: Object,
    compte: Object,
    etapes: Array,
    historique: Array,
});

const onglets = ['Informations', 'Situation', 'Parcours', 'Historique', 'Compte'];
const ongletActif = ref('Informations');

const statutLabel = {
    recommande: 'Recommandé',
    en_formation: 'En formation',
    actif: 'Actif',
    suspendu: 'Suspendu',
    retire: 'Retiré',
};

const etapeStatutLabel = {
    en_attente: 'En attente',
    en_cours: 'En cours',
    termine: 'Terminé',
    ignore: 'Ignoré',
};

const etapeStatutClass = {
    en_attente: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    en_cours: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    termine: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    ignore: 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
};

const etapeEnEdition = ref(null);

const form = useForm({
    statut: '',
    date: '',
    commentaire: '',
});

const editerEtape = (etape) => {
    etapeEnEdition.value = etape.id;
    form.statut = etape.statut;
    form.date = etape.date ?? '';
    form.commentaire = etape.commentaire ?? '';
};

const enregistrerEtape = (etapeId) => {
    form.patch(route('servants.workflow.update', [props.servant.id, etapeId]), {
        preserveScroll: true,
        onSuccess: () => {
            etapeEnEdition.value = null;
        },
    });
};

const compteForm = useForm({
    email: '',
    password: '',
});

const creerCompte = () => {
    compteForm.post(route('servants.account.store', props.servant.id), {
        preserveScroll: true,
        onSuccess: () => compteForm.reset(),
    });
};

const revoquerCompte = () => {
    if (!confirm('Révoquer ce compte de connexion ? Le servant ne pourra plus se connecter.')) return;
    router.delete(route('servants.account.destroy', props.servant.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ servant.prenom }} {{ servant.nom }}
                </h2>
                <Link :href="route('servants.edit', servant.id)" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <div class="border-b border-gray-200 px-6 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6">
                            <button
                                v-for="onglet in onglets"
                                :key="onglet"
                                @click="ongletActif = onglet"
                                class="border-b-2 px-1 py-4 text-sm font-medium"
                                :class="ongletActif === onglet
                                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400'"
                            >
                                {{ onglet }}
                            </button>
                        </nav>
                    </div>

                    <div class="p-6">
                        <!-- Informations personnelles -->
                        <dl v-if="ongletActif === 'Informations'" class="grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Prénom</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.prenom }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Nom</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.nom }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Genre</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.genre ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Téléphone</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.telephone ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Téléphone (appel)</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.telephone_appel ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Pieu</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.pieu ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Date de naissance</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.date_naissance ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Adresse</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ servant.adresse ?? '—' }}</dd>
                            </div>
                        </dl>

                        <!-- Situation actuelle -->
                        <div v-if="ongletActif === 'Situation'">
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Statut actuel</dt>
                            <dd class="mt-1 text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ statutLabel[servant.statut] }}
                            </dd>
                        </div>

                        <!-- Parcours d'intégration -->
                        <div v-if="ongletActif === 'Parcours'" class="space-y-3">
                            <div
                                v-for="etape in etapes"
                                :key="etape.id"
                                class="rounded-md border border-gray-200 p-4 dark:border-gray-700"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ etape.ordre }}. {{ etape.nom }}
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="rounded-full px-2 py-1 text-xs font-medium" :class="etapeStatutClass[etape.statut]">
                                            {{ etapeStatutLabel[etape.statut] }}
                                        </span>
                                        <button
                                            v-if="etapeEnEdition !== etape.id"
                                            @click="editerEtape(etape)"
                                            class="text-xs text-indigo-600 hover:text-indigo-900 dark:text-indigo-400"
                                        >
                                            Modifier
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400" v-if="etapeEnEdition !== etape.id">
                                    <span v-if="etape.date">Le {{ etape.date }}</span>
                                    <span v-if="etape.responsable"> — par {{ etape.responsable }}</span>
                                    <p v-if="etape.commentaire" class="mt-1">{{ etape.commentaire }}</p>
                                </div>

                                <form v-else @submit.prevent="enregistrerEtape(etape.id)" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <select
                                        v-model="form.statut"
                                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="en_attente">En attente</option>
                                        <option value="en_cours">En cours</option>
                                        <option value="termine">Terminé</option>
                                        <option value="ignore">Ignoré</option>
                                    </select>
                                    <input
                                        v-model="form.date"
                                        type="date"
                                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                    <input
                                        v-model="form.commentaire"
                                        type="text"
                                        placeholder="Commentaire"
                                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    />
                                    <div class="sm:col-span-3 flex justify-end gap-2">
                                        <button type="button" @click="etapeEnEdition = null" class="text-sm text-gray-500 dark:text-gray-400">
                                            Annuler
                                        </button>
                                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Historique -->
                        <div v-if="ongletActif === 'Historique'">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Poste</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Shift</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Début</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Fin</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-if="historique.length === 0">
                                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                            Aucune affectation pour le moment.
                                        </td>
                                    </tr>
                                    <tr v-for="h in historique" :key="h.id">
                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ h.poste }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ h.shift }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ h.date_debut }}</td>
                                        <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ h.date_fin ?? '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Compte de connexion -->
                        <div v-if="ongletActif === 'Compte'">
                            <div v-if="compte" class="space-y-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    Ce servant dispose d'un compte de connexion : <strong>{{ compte.email }}</strong>
                                </p>
                                <DangerButton @click="revoquerCompte">Révoquer le compte</DangerButton>
                            </div>
                            <form v-else @submit.prevent="creerCompte" class="max-w-md space-y-4">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Créer un compte permet à ce servant de se connecter et de voir ses propres affectations.
                                </p>
                                <div>
                                    <InputLabel for="compte_email" value="Email" />
                                    <TextInput id="compte_email" v-model="compteForm.email" type="email" class="mt-1 block w-full" required />
                                    <InputError class="mt-2" :message="compteForm.errors.email" />
                                </div>
                                <div>
                                    <InputLabel for="compte_password" value="Mot de passe" />
                                    <TextInput id="compte_password" v-model="compteForm.password" type="password" class="mt-1 block w-full" required />
                                    <InputError class="mt-2" :message="compteForm.errors.password" />
                                </div>
                                <PrimaryButton :disabled="compteForm.processing">Créer le compte</PrimaryButton>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
