<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Checkbox from '@/Components/Checkbox.vue';
import Badge from '@/Components/Badge.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

defineProps({
    roles: Array,
});

const enEdition = ref(null);
const form = useForm({ nom: '', description: '', gere_shifts: false });

const editer = (role) => {
    enEdition.value = role.id;
    form.nom = role.nom;
    form.description = role.description ?? '';
    form.gere_shifts = role.gere_shifts;
};

const enregistrer = (id) => {
    form.put(route('settings.roles.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const showCreateForm = ref(false);
const createForm = useForm({ nom: '', description: '', gere_shifts: false });

const creer = () => {
    createForm.post(route('settings.roles.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreateForm.value = false;
        },
    });
};

const supprimer = async (role) => {
    if (!(await confirmer(`Supprimer le rôle « ${role.nom} » ?`, { danger: true }))) return;
    router.delete(route('settings.roles.destroy', role.id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Rôles" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Rôles' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    Paramètres — Rôles
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">+ Nouveau rôle</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Les rôles marqués « protégé » (Conseil du Temple, Coordonnateur d'équipe, Secrétaire…) portent des permissions
                codées dans l'application et ne peuvent pas être supprimés. Un rôle personnalisé n'a accès qu'au tableau de
                bord et au profil tant qu'aucun accès spécifique ne lui est ouvert.
            </p>

            <form v-if="showCreateForm" @submit.prevent="creer" class="space-y-3 rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div>
                    <InputLabel for="nouveau-nom" value="Nom du rôle" />
                    <TextInput id="nouveau-nom" v-model="createForm.nom" type="text" class="mt-1 block w-full" placeholder="Ex. : Équipe du bureau" required />
                    <InputError class="mt-2" :message="createForm.errors.nom" />
                </div>
                <div>
                    <InputLabel for="nouvelle-description" value="Description (optionnel)" />
                    <textarea
                        id="nouvelle-description"
                        v-model="createForm.description"
                        rows="2"
                        class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                    ></textarea>
                </div>
                <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                    <Checkbox v-model:checked="createForm.gere_shifts" />
                    Ce rôle gère des shifts (accès au dashboard coordinateur, peut être affecté comme responsable d'un Shift)
                </label>
                <div class="flex justify-end">
                    <PrimaryButton :disabled="createForm.processing">Créer le rôle</PrimaryButton>
                </div>
            </form>

            <div v-for="role in roles" :key="role.id" class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div v-if="enEdition !== role.id" class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ role.nom }}</span>
                            <Badge v-if="role.protege" variant="info">Protégé</Badge>
                            <Badge v-if="role.gere_shifts" variant="success">Gère des shifts</Badge>
                        </div>
                        <div class="text-xs uppercase text-neutral-600 dark:text-neutral-400">{{ role.slug }}</div>
                        <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">{{ role.description }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(role)">
                            Modifier
                        </button>
                        <DangerButton
                            v-if="!role.protege"
                            :disabled="role.utilise"
                            :title="role.utilise ? 'Rôle encore attribué à des comptes' : ''"
                            @click="supprimer(role)"
                        >
                            Supprimer
                        </DangerButton>
                    </div>
                </div>
                <form v-else @submit.prevent="enregistrer(role.id)" class="space-y-3">
                    <input
                        v-model="form.nom"
                        type="text"
                        class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                        required
                    />
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                    ></textarea>
                    <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-300">
                        <Checkbox v-model:checked="form.gere_shifts" />
                        Ce rôle gère des shifts
                    </label>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-sm text-neutral-600 dark:text-neutral-400" @click="enEdition = null">Annuler</button>
                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
