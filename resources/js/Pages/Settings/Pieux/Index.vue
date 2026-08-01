<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    pieux: Array,
});

const form = useForm({ nom: '' });

const ajouter = () => {
    form.post(route('settings.pieux.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForm = useForm({ nom: '' });

const editer = (pieu) => {
    enEdition.value = pieu.id;
    editForm.nom = pieu.nom;
};

const enregistrer = (id) => {
    editForm.put(route('settings.pieux.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = async (id) => {
    if (!(await confirmer('Supprimer ce pieu ?', { danger: true }))) return;
    router.delete(route('settings.pieux.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Pieux" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Paramètres — Pieux
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <form @submit.prevent="ajouter" class="flex gap-3 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <TextInput v-model="form.nom" type="text" class="block w-full" placeholder="Nom du pieu" required />
                <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
            </form>

            <div class="rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <ul class="divide-y divide-neutral-100">
                    <li v-if="pieux.length === 0" class="p-6 text-center text-neutral-600">
                        Aucun pieu enregistré.
                    </li>
                    <li v-for="pieu in pieux" :key="pieu.id" class="flex items-center justify-between p-4">
                        <span v-if="enEdition !== pieu.id" class="text-neutral-900">{{ pieu.nom }}</span>
                        <input
                            v-else
                            v-model="editForm.nom"
                            type="text"
                            class="mr-3 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        />
                        <div class="flex items-center gap-2">
                            <template v-if="enEdition === pieu.id">
                                <PrimaryButton @click="enregistrer(pieu.id)">Enregistrer</PrimaryButton>
                                <button type="button" class="text-sm text-neutral-600" @click="enEdition = null">Annuler</button>
                            </template>
                            <template v-else>
                                <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(pieu)">Modifier</button>
                                <DangerButton @click="supprimer(pieu.id)">Supprimer</DangerButton>
                            </template>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
