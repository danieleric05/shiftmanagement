<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

// Confirmation forte de la suppression DÉFINITIVE d'un servant (Conseil du
// Temple) : il faut taper le nom complet du servant ou le mot SUPPRIMER.
const props = defineProps({
    show: { type: Boolean, default: false },
    // { id, prenom, nom }
    servant: { type: Object, required: true },
    // { bilan: { affectations, etapes_parcours, releves, permutations, appels, demandes_changement, notifications, entrees_journal }, compte_lie }
    suppression: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const form = useForm({ confirmation: '' });

const nomComplet = computed(() => `${props.servant.prenom} ${props.servant.nom}`);

const normaliser = (v) => v.trim().replace(/\s+/g, ' ').toLowerCase();
const confirmationValide = computed(() => form.confirmation.trim() === 'SUPPRIMER'
    || normaliser(form.confirmation) === normaliser(nomComplet.value));

const bilan = computed(() => props.suppression.bilan);
const totalLiees = computed(() => bilan.value.affectations
    + bilan.value.etapes_parcours
    + bilan.value.demandes_changement
    + bilan.value.notifications
    + bilan.value.entrees_journal);

watch(() => props.show, (visible) => {
    if (visible) {
        form.reset();
        form.clearErrors();
    }
});

const fermer = () => emit('close');

const supprimer = () => {
    if (!confirmationValide.value) return;
    form.delete(route('servants.destroy', props.servant.id), {
        onSuccess: () => fermer(),
    });
};
</script>

<template>
    <Modal :show="show" max-width="lg" @close="fermer">
        <form class="space-y-4 p-6" @submit.prevent="supprimer">
            <h2 class="text-lg font-semibold text-red-700 dark:text-red-400">
                Supprimer définitivement {{ nomComplet }} ?
            </h2>

            <p class="text-sm text-neutral-700 dark:text-neutral-300">
                Cette action est <strong>irréversible</strong>. Seront effacés, sans possibilité de récupération :
            </p>
            <ul class="list-disc space-y-1 pl-5 text-sm text-neutral-700 dark:text-neutral-300">
                <li>la fiche du servant(e) et sa photo ;</li>
                <li>ses affectations : {{ bilan.affectations }} ;</li>
                <li>son parcours d'intégration : {{ bilan.etapes_parcours }} étape(s) ;</li>
                <li>
                    son historique de relèves, permutations et appels : {{ bilan.demandes_changement }} demande(s)
                    ({{ bilan.releves }} relève(s), {{ bilan.permutations }} permutation(s), {{ bilan.appels }} appel(s)) ;
                </li>
                <li>les notifications et entrées du journal qui le concernent : {{ bilan.notifications }} + {{ bilan.entrees_journal }}.</li>
            </ul>
            <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                Total : {{ totalLiees }} entrée(s) liée(s) supprimée(s).
            </p>
            <p v-if="suppression.compte_lie" class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
                Cette fiche est liée à un compte de connexion : le compte du membre est <strong>conservé</strong>, seul le lien avec la fiche est supprimé.
            </p>
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Pour seulement effacer les données personnelles en gardant l'historique, utilisez plutôt « Anonymiser (RGPD) ».
            </p>

            <div>
                <InputLabel for="confirmation_suppression">
                    Tapez <strong>{{ nomComplet }}</strong> ou <strong>SUPPRIMER</strong> pour confirmer
                </InputLabel>
                <TextInput
                    id="confirmation_suppression"
                    v-model="form.confirmation"
                    type="text"
                    class="mt-1 block w-full"
                    autocomplete="off"
                />
                <InputError class="mt-2" :message="form.errors.confirmation" />
            </div>

            <div class="flex justify-end gap-3">
                <SecondaryButton type="button" @click="fermer">Annuler</SecondaryButton>
                <DangerButton :disabled="!confirmationValide || form.processing" :class="{ 'opacity-25': !confirmationValide || form.processing }">
                    Supprimer définitivement
                </DangerButton>
            </div>
        </form>
    </Modal>
</template>
