<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    servant: Object,
    compte: Object,
    etapes: Array,
    historique: Array,
});

const { confirmer } = useConfirm();

const onglets = ['Informations', 'Situation', 'Parcours', 'Historique', 'Compte', 'Confidentialité'];
const ongletActif = ref('Informations');

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

const revoquerCompte = async () => {
    if (!(await confirmer('Révoquer ce compte de connexion ? Le servant ne pourra plus se connecter.', { danger: true }))) return;
    router.delete(route('servants.account.destroy', props.servant.id), { preserveScroll: true });
};

const anonymiser = async () => {
    if (!(await confirmer(
        `Anonymiser définitivement les données personnelles de ${props.servant.prenom} ${props.servant.nom} ? Le nom, la photo, la date de naissance, le téléphone et l'adresse seront effacés. Son historique d'affectations est conservé mais dissocié de son identité. Cette action est irréversible.`,
        { danger: true },
    ))) return;
    router.patch(route('servants.anonymize', props.servant.id));
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img
                        v-if="servant.a_photo"
                        :src="route('servants.photo', servant.id)"
                        alt="Photo"
                        class="h-10 w-10 rounded-full object-cover ring-1 ring-neutral-200"
                    />
                    <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                        {{ servant.prenom }} {{ servant.nom }}
                    </h2>
                </div>
                <Link :href="route('servants.edit', servant.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div class="border-b border-neutral-100 px-6">
                    <nav class="-mb-px flex space-x-6">
                        <button
                            v-for="onglet in onglets"
                            :key="onglet"
                            @click="ongletActif = onglet"
                            class="border-b-2 px-1 py-4 text-sm font-medium"
                            :class="ongletActif === onglet
                                ? 'border-primary text-primary'
                                : 'border-transparent text-neutral-600 hover:border-neutral-300 hover:text-neutral-900'"
                        >
                            {{ onglet }}
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Informations personnelles -->
                    <dl v-if="ongletActif === 'Informations'" class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Prénom</dt>
                            <dd class="text-neutral-900">{{ servant.prenom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Nom</dt>
                            <dd class="text-neutral-900">{{ servant.nom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Genre</dt>
                            <dd class="text-neutral-900">{{ servant.genre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Téléphone</dt>
                            <dd class="text-neutral-900">{{ servant.telephone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Téléphone (appel)</dt>
                            <dd class="text-neutral-900">{{ servant.telephone_appel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Pieu</dt>
                            <dd class="text-neutral-900">{{ servant.pieu ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Date de naissance</dt>
                            <dd class="text-neutral-900">{{ servant.date_naissance ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Adresse</dt>
                            <dd class="text-neutral-900">{{ servant.adresse ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600">Titre de leadership</dt>
                            <dd class="text-neutral-900">{{ servant.titre_leadership ?? '—' }}</dd>
                        </div>
                    </dl>

                    <!-- Situation actuelle -->
                    <div v-if="ongletActif === 'Situation'">
                        <dt class="text-xs uppercase text-neutral-600">Statut actuel</dt>
                        <dd class="mt-1">
                            <StatusBadge :statut="servant.statut" />
                        </dd>
                    </div>

                    <!-- Parcours d'intégration -->
                    <div v-if="ongletActif === 'Parcours'" class="space-y-3">
                        <div
                            v-for="etape in etapes"
                            :key="etape.id"
                            class="rounded-md border border-neutral-100 p-4"
                        >
                            <div class="flex items-center justify-between">
                                <div class="font-medium text-neutral-900">
                                    {{ etape.ordre }}. {{ etape.nom }}
                                </div>
                                <div class="flex items-center gap-3">
                                    <StatusBadge :statut="etape.statut" />
                                    <button
                                        v-if="etapeEnEdition !== etape.id"
                                        @click="editerEtape(etape)"
                                        class="text-xs font-medium text-primary-light hover:text-primary"
                                    >
                                        Modifier
                                    </button>
                                </div>
                            </div>

                            <div class="mt-2 text-sm text-neutral-600" v-if="etapeEnEdition !== etape.id">
                                <span v-if="etape.date">Le {{ etape.date }}</span>
                                <span v-if="etape.responsable"> — par {{ etape.responsable }}</span>
                                <p v-if="etape.commentaire" class="mt-1">{{ etape.commentaire }}</p>
                            </div>

                            <form v-else @submit.prevent="enregistrerEtape(etape.id)" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <select
                                    v-model="form.statut"
                                    class="rounded-md border-neutral-300 text-sm shadow-sm"
                                >
                                    <option value="en_attente">En attente</option>
                                    <option value="en_cours">En cours</option>
                                    <option value="termine">Terminé</option>
                                    <option value="ignore">Ignoré</option>
                                </select>
                                <input
                                    v-model="form.date"
                                    type="date"
                                    class="rounded-md border-neutral-300 text-sm shadow-sm"
                                />
                                <input
                                    v-model="form.commentaire"
                                    type="text"
                                    placeholder="Commentaire"
                                    class="rounded-md border-neutral-300 text-sm shadow-sm"
                                />
                                <div class="sm:col-span-3 flex justify-end gap-2">
                                    <button type="button" @click="etapeEnEdition = null" class="text-sm text-neutral-600">
                                        Annuler
                                    </button>
                                    <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Historique -->
                    <div v-if="ongletActif === 'Historique'" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-100">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Poste</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Shift</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Début</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Fin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <tr v-if="historique.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-neutral-600">
                                        Aucune affectation pour le moment.
                                    </td>
                                </tr>
                                <tr v-for="h in historique" :key="h.id">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-900">{{ h.poste }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ h.shift }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ h.date_debut }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ h.date_fin ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Compte de connexion -->
                    <div v-if="ongletActif === 'Compte'">
                        <div v-if="compte" class="space-y-4">
                            <p class="text-sm text-neutral-600">
                                Ce servant dispose d'un compte de connexion : <strong>{{ compte.email }}</strong>
                            </p>
                            <DangerButton @click="revoquerCompte">Révoquer le compte</DangerButton>
                        </div>
                        <form v-else @submit.prevent="creerCompte" class="max-w-md space-y-4">
                            <p class="text-sm text-neutral-600">
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

                    <!-- Confidentialité (RGPD) -->
                    <div v-if="ongletActif === 'Confidentialité'" class="max-w-xl space-y-6">
                        <div>
                            <h4 class="text-sm font-semibold text-neutral-900">Droit d'accès et de portabilité</h4>
                            <p class="mt-1 text-sm text-neutral-600">
                                Exporter l'ensemble des données personnelles détenues sur ce servant (identité, parcours, historique d'affectations) au format JSON.
                            </p>
                            <a :href="route('servants.export', servant.id)" class="mt-3 inline-block">
                                <PrimaryButton type="button">Exporter les données</PrimaryButton>
                            </a>
                        </div>

                        <div class="border-t border-neutral-100 pt-6">
                            <h4 class="text-sm font-semibold text-neutral-900">Droit à l'effacement</h4>
                            <p class="mt-1 text-sm text-neutral-600">
                                Anonymise le nom, la photo, la date de naissance, le téléphone et l'adresse de ce servant. Son dossier et son historique d'affectations sont conservés (dissociés de son identité) pour l'intégrité des données de l'organisation. Ses affectations actives sont terminées et son éventuel compte de connexion est révoqué.
                            </p>
                            <DangerButton class="mt-3" @click="anonymiser">Anonymiser (RGPD)</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
