<script setup>
import Badge from '@/Components/Badge.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Check, Copy, LogOut, Pencil, Plus, Search, ShieldAlert, ShieldCheck, ShieldX, Users } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';

const props = defineProps({
    organisations: Array,
    stats: Object,
});

const page = usePage();

// --- Recherche ---
const recherche = ref('');
const organisationsFiltrees = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    if (!q) return props.organisations;
    return props.organisations.filter((o) => o.nom.toLowerCase().includes(q));
});

// --- Statut de licence ---
const toDateInputValue = (value) => (value ? value.substring(0, 10) : '');

const statut = (licenseExpiresAt) => {
    if (!licenseExpiresAt) {
        return { label: 'Illimitée', variant: 'info' };
    }
    const joursRestants = (new Date(licenseExpiresAt) - new Date()) / (1000 * 60 * 60 * 24);
    if (joursRestants < 0) return { label: 'Expirée', variant: 'danger' };
    if (joursRestants <= 14) return { label: 'Expire bientôt', variant: 'warning' };
    return { label: 'Active', variant: 'success' };
};

// --- Édition inline (nom + date) ---
const edits = reactive(
    Object.fromEntries(
        props.organisations.map((o) => [o.id, { nom: o.nom, license_expires_at: toDateInputValue(o.license_expires_at) }]),
    ),
);

const estModifie = (organisation) => {
    const e = edits[organisation.id];
    return e.nom !== organisation.nom || e.license_expires_at !== toDateInputValue(organisation.license_expires_at);
};

const enregistrer = (organisation) => {
    router.patch(route('owner.licenses.update', organisation.id), edits[organisation.id], { preserveScroll: true });
};

// --- Création d'organisation ---
const showCreateModal = ref(false);

const createForm = useForm({
    nom: '',
    admin_nom: '',
    admin_email: '',
    license_expires_at: '',
});

const creerOrganisation = () => {
    createForm.post(route('owner.organisations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            showCreateModal.value = false;
        },
    });
};

// --- Carte d'identifiants (après création) ---
const identifiantsVisibles = ref(true);
const champCopie = ref(null);

const copier = async (valeur, champ) => {
    await navigator.clipboard.writeText(valeur);
    champCopie.value = champ;
    setTimeout(() => (champCopie.value = null), 1500);
};
</script>

<template>
    <Head title="Espace propriétaire" />

    <div class="min-h-screen bg-neutral-50">
        <header class="bg-primary">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15">
                        <Building2 class="h-5 w-5 text-white" stroke-width="2.25" />
                    </div>
                    <div>
                        <p class="font-bold leading-tight text-white">Espace propriétaire</p>
                        <p class="text-xs leading-tight text-primary-100/80">Gestion des organisations clientes</p>
                    </div>
                </div>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-primary-100/90 transition hover:bg-white/10 hover:text-white"
                >
                    <LogOut class="h-4 w-4" />
                    Déconnexion
                </Link>
            </div>
        </header>

        <div class="mx-auto max-w-6xl space-y-6 px-6 py-8">
            <div v-if="page.props.flash?.success" class="rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success-700 ring-1 ring-success/20">
                {{ page.props.flash.success }}
            </div>

            <!-- Carte d'identifiants après création -->
            <div
                v-if="page.props.flash?.credentials && identifiantsVisibles"
                class="relative overflow-hidden rounded-xl bg-primary p-6 shadow-card"
            >
                <button
                    type="button"
                    class="absolute right-4 top-4 text-primary-100/70 hover:text-white"
                    @click="identifiantsVisibles = false"
                >
                    ✕
                </button>
                <p class="text-sm font-medium text-primary-100/80">Nouveaux accès — « {{ page.props.flash.credentials.organisation }} »</p>
                <p class="mb-4 text-xs text-primary-100/60">Ces identifiants ne seront plus affichés après cette page. Transmets-les au client maintenant.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex items-center justify-between rounded-lg bg-white/10 px-4 py-2.5">
                        <div>
                            <p class="text-xs text-primary-100/70">Email</p>
                            <p class="font-mono text-sm text-white">{{ page.props.flash.credentials.email }}</p>
                        </div>
                        <button type="button" class="text-primary-100/80 hover:text-white" @click="copier(page.props.flash.credentials.email, 'email')">
                            <Check v-if="champCopie === 'email'" class="h-4 w-4" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-white/10 px-4 py-2.5">
                        <div>
                            <p class="text-xs text-primary-100/70">Mot de passe temporaire</p>
                            <p class="font-mono text-sm text-white">{{ page.props.flash.credentials.password }}</p>
                        </div>
                        <button type="button" class="text-primary-100/80 hover:text-white" @click="copier(page.props.flash.credentials.password, 'password')">
                            <Check v-if="champCopie === 'password'" class="h-4 w-4" />
                            <Copy v-else class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <StatCard label="Organisations clientes" :value="stats.total" :icon="Building2" tone="primary" />
                <StatCard label="Expirent bientôt" :value="stats.expirantBientot" hint="Sous 14 jours" :icon="ShieldAlert" tone="warning" />
                <StatCard label="Licences expirées" :value="stats.expirees" :icon="ShieldX" tone="danger" />
            </div>

            <!-- Barre d'actions -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full sm:max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-600" />
                    <input
                        v-model="recherche"
                        type="text"
                        placeholder="Rechercher une organisation…"
                        class="w-full rounded-lg border-neutral-300 py-2 pl-9 text-sm shadow-sm"
                    />
                </div>
                <PrimaryButton class="flex items-center gap-1.5" @click="showCreateModal = true">
                    <Plus class="h-4 w-4" />
                    Nouvelle organisation
                </PrimaryButton>
            </div>

            <!-- Liste -->
            <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div v-if="organisationsFiltrees.length === 0" class="p-10 text-center text-neutral-600">
                    <template v-if="recherche">Aucune organisation ne correspond à « {{ recherche }} ».</template>
                    <template v-else>Aucune organisation cliente pour le moment.</template>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Organisation</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Comptes</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Expiration</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="organisation in organisationsFiltrees" :key="organisation.id" class="transition hover:bg-neutral-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <Pencil class="h-3.5 w-3.5 shrink-0 text-neutral-400" />
                                        <input
                                            v-model="edits[organisation.id].nom"
                                            type="text"
                                            class="w-full min-w-[10rem] rounded-md border-transparent bg-transparent px-1.5 py-1 text-sm font-medium text-neutral-900 hover:border-neutral-300 focus:border-neutral-300 focus:bg-white"
                                        />
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1.5 text-sm text-neutral-600">
                                        <Users class="h-3.5 w-3.5" />
                                        {{ organisation.users_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statut(organisation.license_expires_at).variant">
                                        <ShieldCheck v-if="statut(organisation.license_expires_at).label === 'Active'" class="h-3 w-3" />
                                        {{ statut(organisation.license_expires_at).label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <input
                                        v-model="edits[organisation.id].license_expires_at"
                                        type="date"
                                        class="rounded-md border-neutral-300 text-sm shadow-sm"
                                    />
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <SecondaryButton :disabled="!estModifie(organisation)" @click="enregistrer(organisation)">
                                        Enregistrer
                                    </SecondaryButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal de création -->
        <Modal :show="showCreateModal" max-width="lg" @close="showCreateModal = false">
            <form @submit.prevent="creerOrganisation" class="p-6">
                <h2 class="flex items-center gap-2 text-lg font-semibold text-neutral-900">
                    <Building2 class="h-5 w-5 text-primary" />
                    Nouvelle organisation cliente
                </h2>
                <p class="mt-1 text-sm text-neutral-600">
                    Un compte administrateur est créé automatiquement avec un mot de passe temporaire à transmettre au client.
                </p>

                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="nom" value="Nom de l'organisation" />
                        <TextInput id="nom" v-model="createForm.nom" type="text" class="mt-1 block w-full" required autofocus />
                        <InputError :message="createForm.errors.nom" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="admin_nom" value="Nom de l'administrateur" />
                            <TextInput id="admin_nom" v-model="createForm.admin_nom" type="text" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.admin_nom" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="admin_email" value="Email de l'administrateur" />
                            <TextInput id="admin_email" v-model="createForm.admin_email" type="email" class="mt-1 block w-full" required />
                            <InputError :message="createForm.errors.admin_email" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="license_expires_at" value="Date d'expiration (optionnel — laisser vide pour une licence illimitée)" />
                        <TextInput id="license_expires_at" v-model="createForm.license_expires_at" type="date" class="mt-1 block w-full" />
                        <InputError :message="createForm.errors.license_expires_at" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <SecondaryButton type="button" @click="showCreateModal = false">Annuler</SecondaryButton>
                    <PrimaryButton :disabled="createForm.processing">Créer l'organisation</PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
