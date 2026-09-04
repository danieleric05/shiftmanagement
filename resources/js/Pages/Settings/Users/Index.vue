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
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useConfirm } from '@/composables/useConfirm';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const { confirmer } = useConfirm();

const props = defineProps({
    users: Array,
    roles: Array,
    shifts: Array,
});

const optionsShifts = computed(() => props.shifts.map((s) => ({ value: s.id, label: s.nom })));

const gereDesShifts = (u) => props.roles.find((r) => r.id === u.role_id)?.gere_shifts === true;

const shiftAAjouter = reactive({});

const ajouterShift = (u) => {
    const shiftId = shiftAAjouter[u.id];
    if (!shiftId) return;

    router.post(route('shifts.members.store', shiftId), { user_id: u.id }, {
        preserveScroll: true,
        onSuccess: () => (shiftAAjouter[u.id] = ''),
    });
};

const retirerShift = async (shiftId, affectationId) => {
    if (!(await confirmer('Retirer ce shift de sa liste de gestion ?', { danger: true }))) return;
    router.delete(route('shifts.members.destroy', [shiftId, affectationId]), { preserveScroll: true });
};

const { recherche, resultats: usersFiltres } = useTableSearch(() => props.users, ['nom', 'prenom', 'email']);

const showCreateForm = ref(false);

const form = useForm({
    nom: '',
    prenom: '',
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
            nom: u.nom,
            prenom: u.prenom,
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

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Utilisateurs' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    Paramètres — Utilisateurs
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">+ Nouveau compte</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <form v-if="showCreateForm" @submit.prevent="creer" class="grid grid-cols-1 gap-4 rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 sm:grid-cols-2">
                <div>
                    <InputLabel for="prenom" value="Prénom" />
                    <TextInput id="prenom" v-model="form.prenom" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.prenom" />
                </div>
                <div>
                    <InputLabel for="nom" value="Nom" />
                    <TextInput id="nom" v-model="form.nom" type="text" class="mt-1 block w-full" required />
                    <InputError class="mt-2" :message="form.errors.nom" />
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
                    <select id="role_id" v-model="form.role_id" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
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

            <div class="rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div v-if="users.length === 0" class="p-8 text-center text-neutral-600 dark:text-neutral-400">
                    Aucun compte enregistré.
                </div>
                <div v-else-if="usersFiltres.length === 0" class="p-8 text-center text-neutral-600 dark:text-neutral-400">
                    Aucun compte ne correspond à « {{ recherche }} ».
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Nom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Prénom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Email</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Rôle</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Statut</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Shifts gérés</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Lié à un servant</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
                            <tr v-for="u in usersFiltres" :key="u.id">
                                <template v-if="enEdition === u.id">
                                    <td class="px-3 py-2.5 text-sm">
                                        <TextInput v-model="editForms[u.id].nom" type="text" class="block w-full text-xs" required />
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <TextInput v-model="editForms[u.id].prenom" type="text" class="block w-full text-xs" required />
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ u.nom }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-900 dark:text-neutral-100">{{ u.prenom }}</td>
                                </template>
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">{{ u.email }}</td>

                                <template v-if="enEdition === u.id">
                                    <td class="px-3 py-2.5 text-sm">
                                        <select v-model="editForms[u.id].role_id" class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light">
                                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <select v-model="editForms[u.id].statut" class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light">
                                            <option value="actif">Actif</option>
                                            <option value="suspendu">Suspendu</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <div v-if="gereDesShifts(u)" class="flex flex-col gap-1">
                                            <Badge v-for="s in u.shifts_geres" :key="s.affectation_id" variant="neutral" class="w-fit">
                                                {{ s.shift_nom }}
                                                <button type="button" class="ml-1 text-neutral-500 dark:text-neutral-400 hover:text-danger dark:hover:text-danger-400" @click="retirerShift(s.shift_id, s.affectation_id)">×</button>
                                            </Badge>
                                            <div class="flex items-center gap-1">
                                                <SearchableSelect v-model="shiftAAjouter[u.id]" :options="optionsShifts" placeholder="+ Shift…" class="w-32" />
                                                <button type="button" class="text-xs font-medium text-primary-light hover:text-primary" @click="ajouterShift(u)">Ajouter</button>
                                            </div>
                                        </div>
                                        <span v-else class="text-neutral-400">—</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">{{ u.servant_nom ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <div class="flex items-center gap-2">
                                            <PrimaryButton class="text-xs" :disabled="editForms[u.id].processing" @click="enregistrer(u.id)">Enregistrer</PrimaryButton>
                                            <button type="button" class="text-xs text-neutral-600 dark:text-neutral-400" @click="enEdition = null">Annuler</button>
                                        </div>
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">{{ nomRole(u.role_id) }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="u.statut === 'actif' ? 'success' : 'danger'">{{ u.statut === 'actif' ? 'Actif' : 'Suspendu' }}</Badge>
                                        <Badge v-if="u.must_change_password" variant="warning" class="ml-1">Doit changer son mot de passe</Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <div v-if="gereDesShifts(u)" class="flex flex-col gap-1">
                                            <Badge v-for="s in u.shifts_geres" :key="s.affectation_id" variant="neutral" class="w-fit">
                                                {{ s.shift_nom }}
                                                <button type="button" class="ml-1 text-neutral-500 dark:text-neutral-400 hover:text-danger dark:hover:text-danger-400" @click="retirerShift(s.shift_id, s.affectation_id)">×</button>
                                            </Badge>
                                            <div class="flex items-center gap-1">
                                                <SearchableSelect v-model="shiftAAjouter[u.id]" :options="optionsShifts" placeholder="+ Shift…" class="w-32" />
                                                <button type="button" class="text-xs font-medium text-primary-light hover:text-primary" @click="ajouterShift(u)">Ajouter</button>
                                            </div>
                                        </div>
                                        <span v-else class="text-neutral-400">—</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">
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
