<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Badge from '@/Components/Badge.vue';
import ParcoursIntegration from '@/Components/ParcoursIntegration.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    servant: Object,
    compte: Object,
    etapes: Array,
    etapesDisponibles: Array,
    historique: Array,
});

const { confirmer } = useConfirm();

const onglets = ['Informations', 'Situation', 'Parcours', 'Historique', 'Compte', 'Confidentialité'];
const ongletActif = ref('Informations');

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
        `Anonymiser définitivement les données personnelles de ${props.servant.prenom} ${props.servant.nom} ? Le nom, la photo, le téléphone et l'adresse seront effacés. Son historique d'affectations est conservé mais dissocié de son identité. Cette action est irréversible.`,
        { danger: true },
    ))) return;
    router.patch(route('servants.anonymize', props.servant.id));
};

const parcoursTerminees = computed(() => props.etapes.filter((e) => e.statut === 'termine').length);

// « Nouveau » tant que le servant n'a pas atteint le statut Actif (encore
// recommandé ou en formation) ; « Ancien » ensuite, y compris relevé/retiré
// (il a déjà été actif) — déduit du statut, jamais saisi séparément.
const estNouveauServant = computed(() => ['recommande', 'en_formation'].includes(props.servant.statut));

const demarrerParcoursForm = useForm({});
const demarrerParcours = () => {
    demarrerParcoursForm.post(route('servants.workflow.demarrer', props.servant.id), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Servants', href: route('servants.index') }, { label: `${servant.prenom} ${servant.nom}` }]">
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <img
                        v-if="servant.a_photo"
                        :src="route('servants.photo', servant.id)"
                        alt="Photo"
                        class="h-10 w-10 rounded-full object-cover ring-1 ring-neutral-200 dark:ring-neutral-700"
                    />
                    <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                        {{ servant.prenom }} {{ servant.nom }}
                    </h2>
                </div>
                <Link :href="route('servants.edit', servant.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="border-b border-neutral-100 dark:border-neutral-700 px-6">
                    <nav class="-mb-px flex space-x-6">
                        <button
                            v-for="onglet in onglets"
                            :key="onglet"
                            @click="ongletActif = onglet"
                            class="border-b-2 px-1 py-4 text-sm font-medium"
                            :class="ongletActif === onglet
                                ? 'border-primary text-primary'
                                : 'border-transparent text-neutral-600 dark:text-neutral-400 hover:border-neutral-300 dark:hover:border-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100'"
                        >
                            {{ onglet }}
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Informations personnelles -->
                    <dl v-if="ongletActif === 'Informations'" class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Prénom</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.prenom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Nom</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.nom }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Genre</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.genre ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Téléphone</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.telephone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Téléphone (appel)</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.telephone_appel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Pieu</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.pieu ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Date d'appel</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.date_appel ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Date de début</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.date_debut ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Adresse</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.adresse ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Titre de leadership</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100">{{ servant.titre_leadership ?? '—' }}</dd>
                        </div>
                    </dl>

                    <!-- Situation actuelle -->
                    <div v-if="ongletActif === 'Situation'" class="space-y-6">
                        <div>
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Statut actuel</dt>
                            <dd class="mt-1 flex items-center gap-2">
                                <StatusBadge :statut="servant.statut" domain="servant" />
                                <Badge :variant="estNouveauServant ? 'info' : 'neutral'">
                                    {{ estNouveauServant ? 'Nouveau servant' : 'Ancien servant' }}
                                </Badge>
                            </dd>
                        </div>

                        <div class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Parcours d'intégration</dt>
                            <dd class="mt-1">
                                <div v-if="etapes.length > 0" class="flex items-center gap-3">
                                    <span class="text-neutral-900 dark:text-neutral-100">{{ parcoursTerminees }} / {{ etapes.length }} étapes terminées</span>
                                    <button type="button" class="text-sm font-medium text-primary-light hover:text-primary" @click="ongletActif = 'Parcours'">
                                        Voir le détail →
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-3">
                                    <span class="text-sm text-neutral-600 dark:text-neutral-400">Aucun parcours démarré pour ce servant.</span>
                                    <PrimaryButton :disabled="demarrerParcoursForm.processing" @click="demarrerParcours">
                                        Démarrer le parcours
                                    </PrimaryButton>
                                </div>
                            </dd>
                        </div>
                    </div>

                    <!-- Parcours d'intégration -->
                    <ParcoursIntegration
                        v-if="ongletActif === 'Parcours'"
                        :servant-id="servant.id"
                        :etapes="etapes"
                        :etapes-disponibles="etapesDisponibles"
                    />

                    <!-- Historique -->
                    <div v-if="ongletActif === 'Historique'" class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600 dark:text-neutral-400">Poste</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600 dark:text-neutral-400">Shift</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600 dark:text-neutral-400">Début</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600 dark:text-neutral-400">Fin</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
                                <tr v-if="historique.length === 0">
                                    <td colspan="4" class="px-4 py-6 text-center text-neutral-600 dark:text-neutral-400">
                                        Aucune affectation pour le moment.
                                    </td>
                                </tr>
                                <tr v-for="h in historique" :key="h.id">
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-900 dark:text-neutral-100">{{ h.poste }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-400">{{ h.shift }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-400">{{ h.date_debut }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600 dark:text-neutral-400">{{ h.date_fin ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Compte de connexion -->
                    <div v-if="ongletActif === 'Compte'">
                        <div v-if="compte" class="space-y-4">
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                                Ce servant dispose d'un compte de connexion : <strong>{{ compte.email }}</strong>
                            </p>
                            <DangerButton @click="revoquerCompte">Révoquer le compte</DangerButton>
                        </div>
                        <form v-else @submit.prevent="creerCompte" class="max-w-md space-y-4">
                            <p class="text-sm text-neutral-600 dark:text-neutral-400">
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
                            <h4 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Droit d'accès et de portabilité</h4>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Exporter l'ensemble des données personnelles détenues sur ce servant (identité, parcours, historique d'affectations) au format JSON.
                            </p>
                            <a :href="route('servants.export', servant.id)" class="mt-3 inline-block">
                                <PrimaryButton type="button">Exporter les données</PrimaryButton>
                            </a>
                        </div>

                        <div class="border-t border-neutral-100 dark:border-neutral-700 pt-6">
                            <h4 class="text-sm font-semibold text-neutral-900 dark:text-neutral-100">Droit à l'effacement</h4>
                            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Anonymise le nom, la photo, le téléphone et l'adresse de ce servant. Son dossier et son historique d'affectations sont conservés (dissociés de son identité) pour l'intégrité des données de l'organisation. Ses affectations actives sont terminées et son éventuel compte de connexion est révoqué.
                            </p>
                            <DangerButton class="mt-3" @click="anonymiser">Anonymiser (RGPD)</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
