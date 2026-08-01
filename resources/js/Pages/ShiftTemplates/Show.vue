<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

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

const supprimerPoste = async (positionId) => {
    if (!(await confirmer('Supprimer ce poste du modèle ?', { danger: true }))) return;
    router.delete(route('shift-templates.positions.destroy', [props.template.id, positionId]), {
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
                        placeholder="Ex: Coordinateur Adjoint"
                        required
                    />
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </form>

                <ul class="divide-y divide-neutral-100">
                    <li v-if="positions.length === 0" class="py-6 text-center text-neutral-600">
                        Aucun poste défini pour ce modèle.
                    </li>
                    <li v-for="position in positions" :key="position.id" class="flex items-center justify-between py-3">
                        <span class="text-neutral-900">{{ position.ordre }}. {{ position.nom }}</span>
                        <DangerButton @click="supprimerPoste(position.id)">Retirer</DangerButton>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
