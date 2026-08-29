<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    servant: Object,
    etapes: Array,
    etapesDisponibles: Array,
});

const { confirmer } = useConfirm();

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

const ajouterEtapeForm = useForm({ workflow_step_id: '' });

const ajouterEtape = () => {
    ajouterEtapeForm.post(route('servants.workflow.store', props.servant.id), {
        preserveScroll: true,
        onSuccess: () => ajouterEtapeForm.reset(),
    });
};

const retirerEtape = async (etapeId) => {
    if (!(await confirmer('Retirer cette étape du parcours ?', { danger: true }))) return;
    router.delete(route('servants.workflow.destroy', [props.servant.id, etapeId]), { preserveScroll: true });
};
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: `${servant.prenom} ${servant.nom}` }]">
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
                <div class="flex items-center gap-4">
                    <Link :href="route('servants.edit', servant.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                        Modifier
                    </Link>
                    <Link :href="route('dashboard')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600">Téléphone</p>
                        <p class="text-neutral-900">{{ servant.telephone ?? '—' }}</p>
                    </div>
                    <div v-if="servant.titre_leadership" class="text-right">
                        <p class="text-sm text-neutral-600">Titre de leadership</p>
                        <p class="text-neutral-900">{{ servant.titre_leadership }}</p>
                    </div>
                    <StatusBadge :statut="servant.statut" domain="servant" />
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-base font-semibold text-neutral-900">Parcours d'intégration</h3>

                <form
                    v-if="etapesDisponibles.length > 0"
                    @submit.prevent="ajouterEtape"
                    class="mb-4 flex items-end gap-3 rounded-md border border-dashed border-neutral-200 p-4"
                >
                    <div class="flex-1">
                        <InputLabel for="nouvelle-etape" value="Ajouter une étape au parcours" />
                        <select
                            id="nouvelle-etape"
                            v-model="ajouterEtapeForm.workflow_step_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner une étape</option>
                            <option v-for="e in etapesDisponibles" :key="e.id" :value="e.id">{{ e.nom }}</option>
                        </select>
                        <InputError class="mt-1" :message="ajouterEtapeForm.errors.workflow_step_id" />
                    </div>
                    <PrimaryButton :disabled="ajouterEtapeForm.processing">Ajouter</PrimaryButton>
                </form>

                <p v-if="etapes.length === 0" class="text-sm text-neutral-600">
                    Aucune étape de parcours ajoutée pour ce servant.
                </p>

                <div class="space-y-3">
                    <div v-for="etape in etapes" :key="etape.id" class="rounded-md border border-neutral-100 p-4">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-neutral-900">{{ etape.ordre }}. {{ etape.nom }}</div>
                            <div class="flex items-center gap-3">
                                <StatusBadge :statut="etape.statut" />
                                <button
                                    v-if="etapeEnEdition !== etape.id"
                                    @click="editerEtape(etape)"
                                    class="text-xs font-medium text-primary-light hover:text-primary"
                                >
                                    Modifier
                                </button>
                                <button
                                    type="button"
                                    @click="retirerEtape(etape.id)"
                                    class="text-xs font-medium text-danger hover:underline"
                                >
                                    Retirer
                                </button>
                            </div>
                        </div>

                        <div class="mt-2 text-sm text-neutral-600" v-if="etapeEnEdition !== etape.id">
                            <span v-if="etape.date">Le {{ etape.date }}</span>
                            <span v-if="etape.responsable"> — par {{ etape.responsable }}</span>
                            <p v-if="etape.commentaire" class="mt-1">{{ etape.commentaire }}</p>
                        </div>

                        <form v-else @submit.prevent="enregistrerEtape(etape.id)" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <select v-model="form.statut" class="rounded-md border-neutral-300 text-sm shadow-sm">
                                <option value="en_attente">En attente</option>
                                <option value="en_cours">En cours</option>
                                <option value="termine">Terminé</option>
                                <option value="ignore">Ignoré</option>
                            </select>
                            <input v-model="form.date" type="date" class="rounded-md border-neutral-300 text-sm shadow-sm" />
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
