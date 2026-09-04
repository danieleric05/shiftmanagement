<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    servantId: { type: [Number, String], required: true },
    etapes: { type: Array, required: true },
    etapesDisponibles: { type: Array, required: true },
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
    form.patch(route('servants.workflow.update', [props.servantId, etapeId]), {
        preserveScroll: true,
        onSuccess: () => {
            etapeEnEdition.value = null;
        },
    });
};

const ajouterEtapeForm = useForm({ workflow_step_id: '' });

const ajouterEtape = () => {
    ajouterEtapeForm.post(route('servants.workflow.store', props.servantId), {
        preserveScroll: true,
        onSuccess: () => ajouterEtapeForm.reset(),
    });
};

const retirerEtape = async (etapeId) => {
    if (!(await confirmer('Retirer cette étape du parcours ?', { danger: true }))) return;
    router.delete(route('servants.workflow.destroy', [props.servantId, etapeId]), { preserveScroll: true });
};
</script>

<template>
    <div class="space-y-3">
        <form
            v-if="etapesDisponibles.length > 0"
            @submit.prevent="ajouterEtape"
            class="flex items-end gap-3 rounded-md border border-dashed border-neutral-200 p-4 dark:border-neutral-600"
        >
            <div class="flex-1">
                <InputLabel for="nouvelle-etape" value="Ajouter une étape au parcours" />
                <select
                    id="nouvelle-etape"
                    v-model="ajouterEtapeForm.workflow_step_id"
                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                    required
                >
                    <option value="" disabled>Sélectionner une étape</option>
                    <option v-for="e in etapesDisponibles" :key="e.id" :value="e.id">{{ e.nom }}</option>
                </select>
                <InputError class="mt-1" :message="ajouterEtapeForm.errors.workflow_step_id" />
            </div>
            <PrimaryButton :disabled="ajouterEtapeForm.processing">Ajouter</PrimaryButton>
        </form>

        <p v-if="etapes.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
            Aucune étape de parcours ajoutée pour ce servant.
        </p>

        <div
            v-for="etape in etapes"
            :key="etape.id"
            class="rounded-md border border-neutral-100 p-4 dark:border-neutral-700"
        >
            <div class="flex items-center justify-between">
                <div class="font-medium text-neutral-900 dark:text-neutral-100">
                    {{ etape.ordre }}. {{ etape.nom }}
                </div>
                <div class="flex items-center gap-3">
                    <StatusBadge :statut="etape.statut" />
                    <button
                        v-if="etapeEnEdition !== etape.id"
                        @click="editerEtape(etape)"
                        class="text-xs font-medium text-primary-light hover:text-primary dark:hover:text-primary-300"
                    >
                        Modifier
                    </button>
                    <button
                        type="button"
                        @click="retirerEtape(etape.id)"
                        class="text-xs font-medium text-danger hover:underline dark:text-danger-400"
                    >
                        Retirer
                    </button>
                </div>
            </div>

            <div class="mt-2 text-sm text-neutral-600 dark:text-neutral-400" v-if="etapeEnEdition !== etape.id">
                <span v-if="etape.date">Le {{ etape.date }}</span>
                <span v-if="etape.responsable"> — par {{ etape.responsable }}</span>
                <p v-if="etape.commentaire" class="mt-1">{{ etape.commentaire }}</p>
            </div>

            <form v-else @submit.prevent="enregistrerEtape(etape.id)" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <select
                    v-model="form.statut"
                    class="rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                >
                    <option value="en_attente">En attente</option>
                    <option value="en_cours">En cours</option>
                    <option value="termine">Terminé</option>
                    <option value="ignore">Ignoré</option>
                </select>
                <input
                    v-model="form.date"
                    type="date"
                    class="rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                />
                <input
                    v-model="form.commentaire"
                    type="text"
                    placeholder="Commentaire"
                    class="rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500"
                />
                <div class="sm:col-span-3 flex justify-end gap-2">
                    <button type="button" @click="etapeEnEdition = null" class="text-sm text-neutral-600 dark:text-neutral-400">
                        Annuler
                    </button>
                    <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>
