<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Gavel, UserMinus } from '@lucide/vue';

const props = defineProps({
    demandes: Array,
    servants: Array,
    compteurs: Object,
});

const { recherche, resultats: demandesFiltrees } = useTableSearch(() => props.demandes, ['servant']);

const typeLabel = {
    avis: 'Avis',
    retrait: 'Retrait',
    autre: 'Autre',
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
    <Head title="Permutation" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    Permutation
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">
                    + Nouvelle demande
                </PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Avis en attente" :value="compteurs.avis" :icon="Gavel" tone="warning" />
                <StatCard label="Retraits en attente" :value="compteurs.retraits" :icon="UserMinus" tone="warning" />
            </div>

            <form v-if="showCreateForm" @submit.prevent="creerDemande" class="grid grid-cols-1 gap-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 sm:grid-cols-3">
                <div>
                    <InputLabel for="servant_id" value="Servant" />
                    <select
                        id="servant_id"
                        v-model="form.servant_id"
                        class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
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
                        class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
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
                        class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        required
                    ></textarea>
                    <InputError class="mt-2" :message="form.errors.motif" />
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Soumettre</PrimaryButton>
                </div>
            </form>

            <SearchInput v-if="demandes.length > 0" v-model="recherche" placeholder="Rechercher un servant…" />

            <div class="space-y-4">
                <div v-if="demandes.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                    Aucune demande pour le moment.
                </div>
                <div v-else-if="demandesFiltrees.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                    Aucune demande ne correspond à « {{ recherche }} ».
                </div>
                <div
                    v-for="demande in demandesFiltrees"
                    :key="demande.id"
                    class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-medium text-neutral-900">
                                {{ typeLabel[demande.type] }} — {{ demande.servant }}
                            </div>
                            <div class="text-sm text-neutral-600">
                                Demandé par {{ demande.demandeur }} le {{ demande.created_at }}
                            </div>
                            <p class="mt-2 text-sm text-neutral-600">{{ demande.motif }}</p>
                            <div v-if="demande.statut !== 'en_attente'" class="mt-2 text-sm text-neutral-600">
                                Décidé par {{ demande.decideur }} le {{ demande.decided_at }}
                                <span v-if="demande.decision_commentaire"> — {{ demande.decision_commentaire }}</span>
                            </div>
                        </div>
                        <StatusBadge :statut="demande.statut" />
                    </div>

                    <div v-if="demande.statut === 'en_attente'" class="mt-4 flex items-center gap-2">
                        <input
                            v-model="decisionForms[demande.id]"
                            type="text"
                            placeholder="Commentaire de décision (optionnel)"
                            class="block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        />
                        <SecondaryButton @click="valider(demande.id)">Valider</SecondaryButton>
                        <DangerButton @click="rejeter(demande.id)">Rejeter</DangerButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
