<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { libellePagination } from '@/composables/usePagination';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import Badge from '@/Components/Badge.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const { confirmer } = useConfirm();

const props = defineProps({
    users: Object,
    roles: Array,
    shifts: Array,
    filtreRecherche: { type: String, default: '' },
    filtreRole: { type: Number, default: null },
    filtreStatut: { type: String, default: null },
    filtreAcces: { type: String, default: null },
    // Statuts de la personne (Recommandé / Nouveau / Ancien), libellés fournis
    // par le serveur (User::libellesStatut(), repris des servants).
    statuts: { type: Array, default: () => [] },
});

const moi = computed(() => usePage().props.auth?.user?.id);

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

// Recherche côté serveur (liste paginée), même pattern que le journal
// d'activité et les relèves/permutations : debounce 300 ms puis visite Inertia.
const recherche = ref(props.filtreRecherche ?? '');
const roleFiltre = ref(props.filtreRole ?? '');
const statutFiltre = ref(props.filtreStatut ?? '');
const accesFiltre = ref(props.filtreAcces ?? '');

const filtrer = () => {
    router.get(route('settings.users.index'), {
        ...(recherche.value ? { recherche: recherche.value } : {}),
        ...(roleFiltre.value ? { role: roleFiltre.value } : {}),
        ...(statutFiltre.value ? { statut: statutFiltre.value } : {}),
        ...(accesFiltre.value ? { acces: accesFiltre.value } : {}),
    }, { preserveState: true, preserveScroll: true, replace: true });
};

let rechercheTimeout = null;
const rechercher = (valeur) => {
    recherche.value = valeur;
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(filtrer, 300);
};

const changerFiltre = () => {
    clearTimeout(rechercheTimeout);
    filtrer();
};

const filtreApplique = computed(() => Boolean(props.filtreRecherche || props.filtreRole || props.filtreStatut || props.filtreAcces));

const libelleResultats = computed(() => {
    const total = props.users.total ?? 0;
    return `${total} compte${total > 1 ? 's' : ''}${filtreApplique.value ? ` trouvé${total > 1 ? 's' : ''}` : ''}`;
});

const varianteRole = (slug) => ({
    super_admin: 'danger',
    administrateur: 'info',
    coordonnateur_equipe: 'warning',
    secretaire: 'success',
    autres: 'neutral',
}[slug] ?? 'neutral');

const showCreateForm = ref(false);

const form = useForm({
    nom: '',
    prenom: '',
    email: '',
    password: '',
    role_id: '',
    telephone: '',
    statut: 'actif',
    acces_suspendu: false,
});

const creer = async () => {
    if (form.acces_suspendu && !(await confirmer(
        `Créer le compte avec l'accès suspendu ? ${form.prenom} ${form.nom} ne pourra pas se connecter tant que l'accès n'est pas rétabli.`,
        { danger: true },
    ))) return;

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
            acces_suspendu: Boolean(u.acces_suspendu),
            telephone: u.telephone ?? '',
        });
    }

    return editForms[u.id];
};

const editer = (u) => {
    formeEdition(u);
    enEdition.value = u.id;
};

const enregistrer = async (u) => {
    const id = u.id;
    const edition = editForms[id];

    // Confirmation légère : seulement quand on bascule le blocage d'accès.
    if (edition.acces_suspendu !== Boolean(u.acces_suspendu)) {
        const message = edition.acces_suspendu
            ? `Suspendre l'accès au compte de ${u.name} ? Il sera déconnecté et ne pourra plus se connecter.`
            : `Rétablir l'accès au compte de ${u.name} ?`;
        if (!(await confirmer(message, { danger: edition.acces_suspendu }))) return;
    }

    edition.put(route('settings.users.update', id), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimer = async (u) => {
    if (!(await confirmer(`Supprimer le compte de ${u.name} ?`, { danger: true }))) return;
    router.delete(route('settings.users.destroy', u.id), { preserveScroll: true });
};

const nomRole = (roleId) => props.roles.find((r) => r.id === roleId)?.nom ?? '—';

const classeSelect = 'block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light';
const classeCase = 'rounded border-neutral-300 text-danger shadow-sm focus:ring-danger dark:border-neutral-600 dark:bg-neutral-900';
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

        <div class="mx-auto max-w-7xl space-y-6">
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
                <div>
                    <InputLabel for="statut" value="Statut" />
                    <select id="statut" v-model="form.statut" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                        <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.statut" />
                </div>
                <div class="flex items-start gap-2 sm:pt-6">
                    <input id="acces_suspendu" v-model="form.acces_suspendu" type="checkbox" :class="['mt-0.5', classeCase]" aria-describedby="acces_suspendu_aide" />
                    <div>
                        <label for="acces_suspendu" class="text-sm font-medium text-neutral-700 dark:text-neutral-300">Accès au compte suspendu</label>
                        <p id="acces_suspendu_aide" class="text-xs text-neutral-600 dark:text-neutral-400">La personne ne pourra pas se connecter tant que l'accès n'est pas rétabli.</p>
                        <InputError class="mt-2" :message="form.errors.acces_suspendu" />
                    </div>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Créer le compte</PrimaryButton>
                </div>
            </form>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput
                    :model-value="recherche"
                    placeholder="Rechercher un nom ou un e-mail…"
                    label="Rechercher un compte par nom ou e-mail"
                    @update:model-value="rechercher"
                />
                <select
                    v-model="roleFiltre"
                    aria-label="Filtrer par rôle"
                    class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                    @change="changerFiltre"
                >
                    <option value="">Tous les rôles</option>
                    <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                </select>
                <select
                    v-model="statutFiltre"
                    aria-label="Filtrer par statut"
                    class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                    @change="changerFiltre"
                >
                    <option value="">Tous les statuts</option>
                    <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select
                    v-model="accesFiltre"
                    aria-label="Filtrer par accès au compte"
                    class="rounded-lg border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                    @change="changerFiltre"
                >
                    <option value="">Tous les accès</option>
                    <option value="autorise">Accès autorisé</option>
                    <option value="suspendu">Accès suspendu</option>
                </select>
                <p class="text-sm text-neutral-600 dark:text-neutral-400 sm:ml-auto" role="status" aria-live="polite">
                    {{ libelleResultats }}
                </p>
            </div>

            <div class="overflow-hidden rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <caption class="sr-only">Comptes utilisateurs de l'organisation</caption>
                        <thead class="bg-neutral-50 dark:bg-neutral-900">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Nom</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Prénom</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">E-mail</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Rôle</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Statut</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Accès</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Shifts gérés</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Lié à un servant(e)</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700 bg-white dark:bg-neutral-800">
                            <tr v-if="users.data.length === 0">
                                <td colspan="9" class="px-6 py-8 text-center text-neutral-600 dark:text-neutral-400">
                                    <template v-if="filtreRecherche">Aucun compte ne correspond à « {{ filtreRecherche }} ».</template>
                                    <template v-else-if="filtreRole || filtreStatut || filtreAcces">Aucun compte ne correspond à ces filtres.</template>
                                    <template v-else>Aucun compte enregistré.</template>
                                </td>
                            </tr>
                            <tr v-for="u in users.data" :key="u.id" class="transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-700/40">
                                <template v-if="enEdition === u.id">
                                    <td class="px-6 py-4 text-sm">
                                        <TextInput v-model="editForms[u.id].nom" type="text" class="block w-full text-xs" :aria-label="`Nom de ${u.name}`" required />
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <TextInput v-model="editForms[u.id].prenom" type="text" class="block w-full text-xs" :aria-label="`Prénom de ${u.name}`" required />
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ u.nom }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">{{ u.prenom }}</td>
                                </template>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">{{ u.email }}</td>

                                <template v-if="enEdition === u.id">
                                    <td class="px-6 py-4 text-sm">
                                        <select v-model="editForms[u.id].role_id" :aria-label="`Rôle de ${u.name}`" class="block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light">
                                            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.nom }}</option>
                                        </select>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <select v-model="editForms[u.id].statut" :aria-label="`Statut de ${u.name}`" :class="classeSelect">
                                            <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                        <InputError class="mt-1" :message="editForms[u.id].errors.statut" />
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <label class="inline-flex items-center gap-2 text-xs text-neutral-700 dark:text-neutral-300" :title="u.id === moi ? 'Vous ne pouvez pas suspendre votre propre accès' : undefined">
                                            <input
                                                v-model="editForms[u.id].acces_suspendu"
                                                type="checkbox"
                                                :class="classeCase"
                                                :disabled="u.id === moi && !u.acces_suspendu"
                                            />
                                            Accès au compte suspendu
                                        </label>
                                        <InputError class="mt-1" :message="editForms[u.id].errors.acces_suspendu" />
                                    </td>
                                </template>
                                <template v-else>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <Badge v-if="u.role_id" :variant="varianteRole(u.role_slug)">{{ nomRole(u.role_id) }}</Badge>
                                        <span v-else class="text-neutral-400">—</span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex flex-wrap gap-1">
                                            <StatusBadge :statut="u.statut" domain="utilisateur" />
                                            <Badge v-if="u.must_change_password" variant="warning">Doit changer son mot de passe</Badge>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <Badge v-if="u.acces_suspendu" variant="danger">Accès suspendu</Badge>
                                        <Badge v-else variant="success">Autorisé</Badge>
                                    </td>
                                </template>

                                <td class="px-6 py-4 text-sm">
                                    <div v-if="gereDesShifts(u)" class="flex flex-col gap-1">
                                        <Badge v-for="s in u.shifts_geres" :key="s.affectation_id" variant="neutral" class="w-fit">
                                            {{ s.shift_nom }}
                                            <button
                                                type="button"
                                                class="ml-1 rounded text-neutral-500 hover:text-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-danger dark:text-neutral-400 dark:hover:text-danger-400"
                                                :aria-label="`Retirer le shift ${s.shift_nom} de ${u.name}`"
                                                @click="retirerShift(s.shift_id, s.affectation_id)"
                                            >
                                                <span aria-hidden="true">×</span>
                                            </button>
                                        </Badge>
                                        <div class="flex items-center gap-1">
                                            <SearchableSelect v-model="shiftAAjouter[u.id]" :options="optionsShifts" placeholder="+ Shift…" class="w-32" />
                                            <button
                                                type="button"
                                                class="rounded text-xs font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                                                :aria-label="`Ajouter le shift sélectionné à ${u.name}`"
                                                @click="ajouterShift(u)"
                                            >
                                                Ajouter
                                            </button>
                                        </div>
                                    </div>
                                    <span v-else class="text-neutral-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">
                                    <Link v-if="u.servant_id" :href="route('servants.show', u.servant_id)" class="rounded font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light">{{ u.servant_nom }}</Link>
                                    <span v-else>—</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <div v-if="enEdition === u.id" class="flex items-center justify-end gap-2">
                                        <PrimaryButton class="text-xs" :disabled="editForms[u.id].processing" :aria-label="`Enregistrer le compte de ${u.name}`" @click="enregistrer(u)">Enregistrer</PrimaryButton>
                                        <button
                                            type="button"
                                            class="rounded text-xs text-neutral-600 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-400 dark:hover:text-neutral-100"
                                            :aria-label="`Annuler la modification du compte de ${u.name}`"
                                            @click="enEdition = null"
                                        >
                                            Annuler
                                        </button>
                                    </div>
                                    <div v-else class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                                            :aria-label="`Modifier le compte de ${u.name}`"
                                            @click="editer(u)"
                                        >
                                            Modifier
                                        </button>
                                        <DangerButton class="text-xs" :aria-label="`Supprimer le compte de ${u.name}`" @click="supprimer(u)">Supprimer</DangerButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <nav v-if="users.links?.length > 3" aria-label="Pagination des comptes" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in users.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                        v-html="libellePagination(link.label)"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        :aria-current="link.active ? 'page' : undefined"
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700'"
                        v-html="libellePagination(link.label)"
                    />
                </template>
            </nav>
        </div>
    </AuthenticatedLayout>
</template>
