<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { ChevronUp, ChevronDown } from '@lucide/vue';

const props = defineProps({
    template: Object,
    positions: Array,
});

const { confirmer } = useConfirm();

const form = useForm({
    nom: '',
});

const ajouterPoste = () => {
    form.post(route('shift-templates.positions.store', props.template.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForms = reactive({});

const editerPoste = (position) => {
    editForms[position.id] = useForm({ nom: position.nom });
    enEdition.value = position.id;
};

const enregistrerPoste = (positionId) => {
    editForms[positionId].put(route('shift-templates.positions.update', [props.template.id, positionId]), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimerPoste = async (positionId) => {
    if (!(await confirmer('Supprimer ce poste du modèle ?', { danger: true }))) return;
    router.delete(route('shift-templates.positions.destroy', [props.template.id, positionId]), {
        preserveScroll: true,
    });
};

const deplacerPoste = (positionId, direction) => {
    router.patch(route('shift-templates.positions.move', [props.template.id, positionId]), { direction }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="template.nom" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    {{ template.nom }}
                </h2>
                <Link :href="route('shift-templates.edit', template.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <Link :href="route('shift-templates.index')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>

            <div v-if="template.description" class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <p class="text-neutral-600">{{ template.description }}</p>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-lg font-medium text-neutral-900">Postes du modèle</h3>

                <form @submit.prevent="ajouterPoste" class="mb-6 flex gap-3">
                    <TextInput
                        v-model="form.nom"
                        type="text"
                        class="block w-full"
                        placeholder="Ex: Coordonnateur Adjoint"
                        required
                    />
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </form>

                <ul class="divide-y divide-neutral-100">
                    <li v-if="positions.length === 0" class="py-6 text-center text-neutral-600">
                        Aucun poste défini pour ce modèle.
                    </li>
                    <li v-for="(position, index) in positions" :key="position.id" class="flex items-center justify-between py-3 gap-3">
                        <template v-if="enEdition === position.id">
                            <form @submit.prevent="enregistrerPoste(position.id)" class="flex flex-1 items-start gap-2">
                                <div class="flex-1">
                                    <TextInput v-model="editForms[position.id].nom" type="text" class="block w-full" required />
                                    <InputError class="mt-1" :message="editForms[position.id].errors.nom" />
                                </div>
                                <PrimaryButton :disabled="editForms[position.id].processing">Enregistrer</PrimaryButton>
                                <button type="button" class="text-sm text-neutral-600" @click="enEdition = null">Annuler</button>
                            </form>
                        </template>
                        <template v-else>
                            <span class="text-neutral-900">{{ position.ordre }}. {{ position.nom }}</span>
                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    :disabled="index === 0"
                                    class="rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Monter"
                                    @click="deplacerPoste(position.id, 'haut')"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="index === positions.length - 1"
                                    class="rounded p-1 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-900 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Descendre"
                                    @click="deplacerPoste(position.id, 'bas')"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                                <button type="button" class="ml-2 text-sm font-medium text-primary-light hover:text-primary" @click="editerPoste(position)">
                                    Modifier
                                </button>
                                <DangerButton @click="supprimerPoste(position.id)">Retirer</DangerButton>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
