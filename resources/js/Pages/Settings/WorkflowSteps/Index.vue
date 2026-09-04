<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    etapes: Array,
});

const form = useForm({ cle: '', nom: '' });

const ajouter = () => {
    form.post(route('settings.workflow-steps.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForm = useForm({ nom: '', ordre: 1 });

const editer = (etape) => {
    enEdition.value = etape.id;
    editForm.nom = etape.nom;
    editForm.ordre = etape.ordre;
};

const enregistrer = (id) => {
    editForm.put(route('settings.workflow-steps.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = async (id) => {
    if (!(await confirmer("Supprimer cette étape ? Elle sera retirée du parcours de tous les servants.", { danger: true }))) return;
    router.delete(route('settings.workflow-steps.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Étapes du parcours" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Étapes du parcours' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Paramètres — Étapes du parcours d'intégration
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <form @submit.prevent="ajouter" class="grid grid-cols-1 gap-4 rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 sm:grid-cols-3">
                <div>
                    <InputLabel for="cle" value="Clé (technique)" />
                    <TextInput id="cle" v-model="form.cle" type="text" class="mt-1 block w-full" placeholder="ex: entretien_final" required />
                </div>
                <div>
                    <InputLabel for="nom" value="Nom affiché" />
                    <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" placeholder="Ex: Entretien final" required />
                </div>
                <div class="flex items-end">
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <ul class="divide-y divide-neutral-100 dark:divide-neutral-700">
                    <li v-if="etapes.length === 0" class="p-6 text-center text-neutral-600 dark:text-neutral-400">
                        Aucune étape définie.
                    </li>
                    <li v-for="etape in etapes" :key="etape.id" class="p-4">
                        <div v-if="enEdition !== etape.id" class="flex items-center justify-between">
                            <span class="text-neutral-900 dark:text-neutral-100">{{ etape.ordre }}. {{ etape.nom }}</span>
                            <div class="flex items-center gap-3">
                                <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(etape)">
                                    Modifier
                                </button>
                                <DangerButton @click="supprimer(etape.id)">Supprimer</DangerButton>
                            </div>
                        </div>
                        <form v-else @submit.prevent="enregistrer(etape.id)" class="flex items-center gap-3">
                            <input
                                v-model="editForm.nom"
                                type="text"
                                class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                            />
                            <input
                                v-model.number="editForm.ordre"
                                type="number"
                                min="1"
                                class="block w-24 rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                            />
                            <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
                            <button type="button" class="text-sm text-neutral-600 dark:text-neutral-400" @click="enEdition = null">Annuler</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
