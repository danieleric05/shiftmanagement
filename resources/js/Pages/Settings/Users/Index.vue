<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Badge from '@/Components/Badge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useConfirm } from '@/composables/useConfirm';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, reactive } from 'vue';

const { confirmer } = useConfirm();

const props = defineProps({
    users: Array,
    roles: Array,
});

const { recherche, resultats: usersFiltres } = useTableSearch(() => props.users, ['name', 'email']);

const showCreateForm = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    role_id: '',
    telephone: '',
});

const creer = () => {
    form.post(route('settings.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

const editForms = reactive({});
const enEdition = ref(null);

const formeEdition = (u) => {
    if (!editForms[u.id]) {
        editForms[u.id] = useForm({
            role_id: u.role_id ?? '',
            statut: u.statut,
            telephone: u.telephone ?? '',
        });
    }

    return editForms[u.id];
};

const editer = (u) => {
    formeEdition(u);
    enEdition.value = u.id;
};

const enregistrer = (id) => {
    editForms[id].put(route('settings.users.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = async (u) => {
    if (!(await confirmer(`Supprimer le compte de ${u.name} ?`, { danger: true }))) return;
    router.delete(route('settings.users.destroy', u.id), { preserveScroll: true });
};

const nomRole = (roleId) => props.roles.find((r) => r.id === roleId)?.nom ?? '—';
</script>

<template>
    <Head title="Utilisateurs" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    Paramètres — Utilisateurs
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">+ Nouveau compte</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <form v-if="showCreateForm" @submit.prevent="creer" class="grid grid-cols-1 gap-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 sm:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Nom" />
                    <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.name" />
                </div>
                <div>
                    <InputLabel for="email" value="Email" />
                    <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
                <div>
                    <InputLabel for="password" value="Mot de passe temporaire" />
                    <TextInput id="password" v-model="form.password" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>
                <div>
                    <InputLabel for="role_id" value="Rôle" />
                    <select id="role_id" v-model="form.role_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.role_id" />
                </div>
                <div>
                    <InputLabel for="telephone" value="Téléphone (optionnel)" />
                    <TextInput id="telephone" v-model="form.telephone" type="text" class="mt-1 block w-full" />
                    <InputError class="mt-2" :message="form.errors.telephone" />
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Créer le compte</PrimaryButton>
                </div>
            </form>

            <SearchInput v-if="users.length > 0" v-model="recherche" placeholder="Rechercher un nom ou un email…" />

            <div class="rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div v-if="users.length === 0" class="p-8 text-center text-neutral-600">
                    Aucun compte enregistré.
                </div>
                <div v-else-if="usersFiltres.length === 0" class="p-8 text-center text-neutral-600">
                    Aucun compte ne correspond à « {{ recherche }} ».
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Email</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Rôle</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Statut</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Lié à un servant</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="u in usersFiltres" :key="u.id">
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900">{{ u.name }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600">{{ u.email }}</td>

                                <template v-if="enEdition === u.id">
                                    <td class="px-3 py-2.5 text-sm">
                                        <select v-model="editForms[u.id].role_id" class="block w-full rounded-md border-neutral-300 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light">
                                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <select v-model="editForms[u.id].statut" class="block w-full rounded-md border-neutral-300 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light">
                                            <option value="actif">Actif</option>
                                            <option value="suspendu">Suspendu</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600">{{ u.servant_nom ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <div class="flex items-center gap-2">
                                            <PrimaryButton class="text-xs" :disabled="editForms[u.id].processing" @click="enregistrer(u.id)">Enregistrer</PrimaryButton>
                                            <button type="button" class="text-xs text-neutral-600" @click="enEdition = null">Annuler</button>
                                        </div>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600">{{ nomRole(u.role_id) }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="u.statut === 'actif' ? 'success' : 'danger'">{{ u.statut === 'actif' ? 'Actif' : 'Suspendu' }}</Badge>
                                        <Badge v-if="u.must_change_password" variant="warning" class="ml-1">Doit changer son mot de passe</Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600">
                                        <Link v-if="u.servant_id" :href="route('servants.show', u.servant_id)" class="text-primary-light hover:text-primary">{{ u.servant_nom }}</Link>
                                        <span v-else>—</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="font-medium text-primary-light hover:text-primary" @click="editer(u)">Modifier</button>
                                            <DangerButton class="text-xs" @click="supprimer(u)">Supprimer</DangerButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
