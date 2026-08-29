<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import Badge from '@/Components/Badge.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirmer } = useConfirm();

const props = defineProps({
    pieux: Array,
});

const typeLabel = { mission: 'Mission', district: 'District', pieu: 'Pieu' };
const typeVariant = { mission: 'info', district: 'warning', pieu: 'neutral' };

// Le parent attendu pour un type donné (une Mission n'a pas de parent).
const typeParentAttendu = { mission: null, district: 'mission', pieu: 'district' };

const parentsPossibles = (type) => props.pieux.filter((p) => p.type === typeParentAttendu[type]);

const form = useForm({ nom: '', type: 'pieu', parent_id: '' });

const ajouter = () => {
    form.transform((data) => ({ ...data, parent_id: data.parent_id || null })).post(route('settings.pieux.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForm = useForm({ nom: '', type: 'pieu', parent_id: '' });

const editer = (pieu) => {
    enEdition.value = pieu.id;
    editForm.nom = pieu.nom;
    editForm.type = pieu.type;
    editForm.parent_id = pieu.parent_id ?? '';
};

const enregistrer = (id) => {
    editForm.transform((data) => ({ ...data, parent_id: data.parent_id || null })).put(route('settings.pieux.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = async (pieu) => {
    if (!(await confirmer(`Supprimer « ${pieu.nom} » ?`, { danger: true }))) return;
    router.delete(route('settings.pieux.destroy', pieu.id), { preserveScroll: true });
};

const nomParent = (pieu) => props.pieux.find((p) => p.id === pieu.parent_id)?.nom;

const pieuxTries = computed(() => {
    const ordre = { mission: 0, district: 1, pieu: 2 };

    return [...props.pieux].sort((a, b) => ordre[a.type] - ordre[b.type] || a.nom.localeCompare(b.nom));
});
</script>

<template>
    <Head title="Pieux" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Pieux' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Paramètres — Pieux, Districts &amp; Missions
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <p class="text-sm text-neutral-600">
                Un servant peut être rattaché soit à un Pieu, soit directement à un District, soit directement à une
                Mission — utile quand l'information exacte n'est pas connue.
            </p>

            <form @submit.prevent="ajouter" class="space-y-3 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <InputLabel for="nouveau-nom" value="Nom" />
                        <TextInput id="nouveau-nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                        <InputError class="mt-1" :message="form.errors.nom" />
                    </div>
                    <div>
                        <InputLabel for="nouveau-type" value="Type" />
                        <select
                            id="nouveau-type"
                            v-model="form.type"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            @change="form.parent_id = ''"
                        >
                            <option value="mission">Mission</option>
                            <option value="district">District</option>
                            <option value="pieu">Pieu</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.type" />
                    </div>
                </div>
                <div v-if="form.type !== 'mission'">
                    <InputLabel for="nouveau-parent" :value="form.type === 'pieu' ? 'District parent (optionnel)' : 'Mission parente (optionnel)'" />
                    <select id="nouveau-parent" v-model="form.parent_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm">
                        <option value="">Aucun</option>
                        <option v-for="p in parentsPossibles(form.type)" :key="p.id" :value="p.id">{{ p.nom }}</option>
                    </select>
                    <InputError class="mt-1" :message="form.errors.parent_id" />
                </div>
                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <div class="rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <ul class="divide-y divide-neutral-100">
                    <li v-if="pieux.length === 0" class="p-6 text-center text-neutral-600">
                        Aucune unité enregistrée.
                    </li>
                    <li v-for="pieu in pieuxTries" :key="pieu.id" class="p-4">
                        <div v-if="enEdition !== pieu.id" class="flex items-center justify-between">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-neutral-900">{{ pieu.nom }}</span>
                                    <Badge :variant="typeVariant[pieu.type]">{{ typeLabel[pieu.type] }}</Badge>
                                </div>
                                <p v-if="nomParent(pieu)" class="mt-0.5 text-xs text-neutral-500">sous {{ nomParent(pieu) }}</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(pieu)">
                                    Modifier
                                </button>
                                <DangerButton @click="supprimer(pieu)">Supprimer</DangerButton>
                            </div>
                        </div>
                        <form v-else @submit.prevent="enregistrer(pieu.id)" class="space-y-3">
                            <input v-model="editForm.nom" type="text" class="block w-full rounded-md border-neutral-300 text-sm shadow-sm" required />
                            <select v-model="editForm.type" class="block w-full rounded-md border-neutral-300 text-sm shadow-sm" @change="editForm.parent_id = ''">
                                <option value="mission">Mission</option>
                                <option value="district">District</option>
                                <option value="pieu">Pieu</option>
                            </select>
                            <select v-if="editForm.type !== 'mission'" v-model="editForm.parent_id" class="block w-full rounded-md border-neutral-300 text-sm shadow-sm">
                                <option value="">Aucun</option>
                                <option v-for="p in parentsPossibles(editForm.type)" :key="p.id" :value="p.id">{{ p.nom }}</option>
                            </select>
                            <div class="flex justify-end gap-2">
                                <button type="button" class="text-sm text-neutral-600" @click="enEdition = null">Annuler</button>
                                <PrimaryButton :disabled="editForm.processing">Enregistrer</PrimaryButton>
                            </div>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
