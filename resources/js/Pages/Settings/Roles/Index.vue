<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    roles: Array,
});

const enEdition = ref(null);
const form = useForm({ nom: '', description: '' });

const editer = (role) => {
    enEdition.value = role.id;
    form.nom = role.nom;
    form.description = role.description ?? '';
};

const enregistrer = (id) => {
    form.put(route('settings.roles.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};
</script>

<template>
    <Head title="Rôles" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Paramètres — Rôles
            </h2>
        </template>

        <div class="mx-auto max-w-3xl space-y-4">
            <div v-for="role in roles" :key="role.id" class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div v-if="enEdition !== role.id" class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-neutral-900">{{ role.nom }}</div>
                        <div class="text-xs uppercase text-neutral-600">{{ role.slug }}</div>
                        <p class="mt-1 text-sm text-neutral-600">{{ role.description }}</p>
                    </div>
                    <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(role)">
                        Modifier
                    </button>
                </div>
                <form v-else @submit.prevent="enregistrer(role.id)" class="space-y-3">
                    <input
                        v-model="form.nom"
                        type="text"
                        class="block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                        required
                    />
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                    ></textarea>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="text-sm text-neutral-600" @click="enEdition = null">Annuler</button>
                        <PrimaryButton :disabled="form.processing">Enregistrer</PrimaryButton>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
