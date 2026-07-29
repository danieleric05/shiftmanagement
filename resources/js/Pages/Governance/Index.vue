<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    demandes: Array,
    servants: Array,
    compteurs: Object,
});

const typeLabel = {
    avis: 'Avis',
    retrait: 'Retrait',
    autre: 'Autre',
};

const statutLabel = {
    en_attente: 'En attente',
    validee: 'Validée',
    rejetee: 'Rejetée',
};

const statutClass = {
    en_attente: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    validee: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    rejetee: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const showCreateForm = ref(false);

const form = useForm({
    servant_id: '',
    type: 'avis',
    motif: '',
});

const creerDemande = () => {
    form.post(route('governance.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

const decisionForms = ref({});

const valider = (id) => {
    router.patch(route('governance.validate', id), {
        decision_commentaire: decisionForms.value[id] ?? '',
    }, { preserveScroll: true });
};

const rejeter = (id) => {
    router.patch(route('governance.reject', id), {
        decision_commentaire: decisionForms.value[id] ?? '',
    }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Gouvernance" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    Gouvernance
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">
                    + Nouvelle demande
                </PrimaryButton>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-lg bg-white p-4 text-center shadow-sm dark:bg-gray-800">
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ compteurs.avis }}</p>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Avis en attente</p>
                    </div>
                    <div class="rounded-lg bg-white p-4 text-center shadow-sm dark:bg-gray-800">
                        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ compteurs.retraits }}</p>
                        <p class="text-xs uppercase text-gray-500 dark:text-gray-400">Retraits en attente</p>
                    </div>
                </div>

                <form v-if="showCreateForm" @submit.prevent="creerDemande" class="grid grid-cols-1 gap-4 rounded-md bg-white p-6 shadow-sm dark:bg-gray-800 sm:grid-cols-3">
                    <div>
                        <InputLabel for="servant_id" value="Servant" />
                        <select
                            id="servant_id"
                            v-model="form.servant_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="s in servants" :key="s.id" :value="s.id">{{ s.nom_complet }}</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.servant_id" />
                    </div>
                    <div>
                        <InputLabel for="type" value="Type" />
                        <select
                            id="type"
                            v-model="form.type"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        >
                            <option value="avis">Avis</option>
                            <option value="retrait">Retrait</option>
                            <option value="autre">Autre</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.type" />
                    </div>
                    <div class="sm:col-span-3">
                        <InputLabel for="motif" value="Motif" />
                        <textarea
                            id="motif"
                            v-model="form.motif"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.motif" />
                    </div>
                    <div class="sm:col-span-3 flex justify-end">
                        <PrimaryButton :disabled="form.processing">Soumettre</PrimaryButton>
                    </div>
                </form>

                <div class="space-y-4">
                    <div v-if="demandes.length === 0" class="rounded-md bg-white p-8 text-center text-gray-500 shadow-sm dark:bg-gray-800 dark:text-gray-400">
                        Aucune demande pour le moment.
                    </div>
                    <div
                        v-for="demande in demandes"
                        :key="demande.id"
                        class="rounded-md bg-white p-6 shadow-sm dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ typeLabel[demande.type] }} — {{ demande.servant }}
                                </div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Demandé par {{ demande.demandeur }} le {{ demande.created_at }}
                                </div>
                                <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ demande.motif }}</p>
                                <div v-if="demande.statut !== 'en_attente'" class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                    Décidé par {{ demande.decideur }} le {{ demande.decided_at }}
                                    <span v-if="demande.decision_commentaire"> — {{ demande.decision_commentaire }}</span>
                                </div>
                            </div>
                            <span class="rounded-full px-2 py-1 text-xs font-medium" :class="statutClass[demande.statut]">
                                {{ statutLabel[demande.statut] }}
                            </span>
                        </div>

                        <div v-if="demande.statut === 'en_attente'" class="mt-4 flex items-center gap-2">
                            <input
                                v-model="decisionForms[demande.id]"
                                type="text"
                                placeholder="Commentaire de décision (optionnel)"
                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            />
                            <SecondaryButton @click="valider(demande.id)">Valider</SecondaryButton>
                            <DangerButton @click="rejeter(demande.id)">Rejeter</DangerButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
