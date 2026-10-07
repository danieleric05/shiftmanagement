<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

// Confirmation de réintégration d'un servant relevé (Conseil du Temple),
// partagée entre la page « Servant(e)s relevé(e)s » et la fiche du servant.
// Le replacement sur un poste est optionnel ; le serveur revérifie le genre
// et l'unicité des postes (AffectationServant).
const props = defineProps({
    show: { type: Boolean, default: false },
    // { id, nom (nom complet), genre }
    servant: { type: Object, default: null },
    // [{ id, nom, genre, postes: [{ id, nom }] }]
    shifts: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const replacer = ref(false);

const form = useForm({
    shift_id: '',
    shift_template_position_id: '',
    commentaire: '',
});

const shiftsCompatibles = computed(() => props.shifts.filter(
    (s) => !props.servant?.genre || s.genre === props.servant.genre,
));

const postes = computed(() => shiftsCompatibles.value.find((s) => s.id === Number(form.shift_id))?.postes ?? []);

watch(() => form.shift_id, () => {
    form.shift_template_position_id = '';
});

watch(() => props.show, (visible) => {
    if (visible) {
        replacer.value = false;
        form.reset();
        form.clearErrors();
    }
});

const fermer = () => emit('close');

const valider = () => {
    form
        .transform((data) => (replacer.value
            ? data
            : { commentaire: data.commentaire }))
        .post(route('servants.reintegrer', props.servant.id), {
            preserveScroll: true,
            onSuccess: () => fermer(),
        });
};

const peutValider = computed(() => !form.processing
    && (!replacer.value || (form.shift_id !== '' && form.shift_template_position_id !== '')));
</script>

<template>
    <Modal :show="show" max-width="lg" @close="fermer">
        <form v-if="servant" class="space-y-5 p-6" @submit.prevent="valider">
            <div>
                <h2 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    Réintégrer {{ servant.nom }} ?
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    La relève est close mais reste dans l'historique. Le servant(e) repasse au statut « Ancien », quel que soit l’avancement de son parcours.
                    L'action est inscrite dans la fiche et dans le journal d'activité.
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                <input v-model="replacer" type="checkbox" class="rounded border-neutral-300 text-primary shadow-sm dark:border-neutral-600 dark:bg-neutral-900" />
                Replacer directement sur un poste d'un shift (facultatif)
            </label>

            <div v-if="replacer" class="grid gap-4 sm:grid-cols-2">
                <div>
                    <InputLabel for="reintegration_shift" value="Shift" />
                    <select
                        id="reintegration_shift"
                        v-model="form.shift_id"
                        class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                    >
                        <option value="">Choisir un shift…</option>
                        <option v-for="s in shiftsCompatibles" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_id" />
                    <p v-if="shiftsCompatibles.length === 0" class="mt-2 text-xs text-neutral-500 dark:text-neutral-400">
                        Aucun shift compatible ne propose de poste disponible.
                    </p>
                </div>
                <div>
                    <InputLabel for="reintegration_poste" value="Poste" />
                    <select
                        id="reintegration_poste"
                        v-model="form.shift_template_position_id"
                        :disabled="!form.shift_id"
                        class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm disabled:opacity-50 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                    >
                        <option value="">Choisir un poste…</option>
                        <option v-for="p in postes" :key="p.id" :value="p.id">{{ p.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_template_position_id" />
                </div>
            </div>

            <div>
                <InputLabel for="reintegration_commentaire" value="Commentaire (facultatif)" />
                <textarea
                    id="reintegration_commentaire"
                    v-model="form.commentaire"
                    rows="2"
                    maxlength="1000"
                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                />
                <InputError class="mt-2" :message="form.errors.commentaire" />
            </div>

            <div class="flex justify-end gap-3">
                <SecondaryButton type="button" @click="fermer">Annuler</SecondaryButton>
                <PrimaryButton :disabled="!peutValider" :class="{ 'opacity-25': form.processing }">
                    Réintégrer
                </PrimaryButton>
            </div>
        </form>
    </Modal>
</template>
