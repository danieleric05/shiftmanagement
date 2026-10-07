<script setup>
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import StatCard from '@/Components/StatCard.vue';
import TextInput from '@/Components/TextInput.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { Building2, Check, Copy, LogOut, Pencil, Plus, Search, ShieldAlert, ShieldCheck, ShieldX, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';

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

// Date affichée en JJ/MM/AAAA, sans conversion de fuseau horaire.
const dateAffichee = (value) => {
    const iso = toDateInputValue(value);
    if (!iso) return null;
    const [annee, mois, jour] = iso.split('-');
    return `${jour}/${mois}/${annee}`;
};

const statut = (licenseExpiresAt) => {
    if (!licenseExpiresAt) {
        return { label: 'Illimitée', variant: 'info', rang: 3 };
    }
    const joursRestants = (new Date(licenseExpiresAt) - new Date()) / (1000 * 60 * 60 * 24);
    if (joursRestants < 0) return { label: 'Expirée', variant: 'danger', rang: 0 };
    if (joursRestants <= 14) return { label: 'Expire bientôt', variant: 'warning', rang: 1 };
    return { label: 'Active', variant: 'success', rang: 2 };
};

// Tri client (liste complète) ; ordre par défaut : nom (serveur). Le statut
// se trie par urgence (Expirée → Expire bientôt → Active → Illimitée).
const colonnes = [
    { cle: 'nom', libelle: 'Organisation', triable: true, principale: true, priorite: 1, largeurMin: 220 },
    { cle: 'users_count', libelle: 'Comptes', triable: true, priorite: 2, largeurMin: 112, largeur: '7rem', alignement: 'fin' },
    { cle: 'statut', libelle: 'Statut', triable: true, priorite: 1, largeurMin: 140, valeur: (o) => statut(o.license_expires_at).label, valeurTri: (o) => statut(o.license_expires_at).rang },
    { cle: 'expiration', libelle: 'Expiration', triable: true, priorite: 2, largeurMin: 120, valeur: (o) => dateAffichee(o.license_expires_at) ?? 'Aucune', valeurTri: (o) => toDateInputValue(o.license_expires_at) },
];

const compteur = computed(() => {
    const n = organisationsFiltrees.value.length;
    return `${n} organisation${n > 1 ? 's' : ''}${recherche.value ? ` trouvée${n > 1 ? 's' : ''}` : ''}`;
});

// --- Modification de la licence (fenêtre modale, plus d'édition en ligne) ---
const enEdition = ref(null);
const edition = useForm({ nom: '', license_expires_at: '' });

const editer = (organisation) => {
    edition.defaults({ nom: organisation.nom, license_expires_at: toDateInputValue(organisation.license_expires_at) });
    edition.reset();
    edition.clearErrors();
    enEdition.value = organisation;
};

const fermerEdition = () => (enEdition.value = null);

const enregistrer = () => {
    if (!enEdition.value) return;
    edition.patch(route('owner.licenses.update', enEdition.value.id), {
        preserveScroll: true,
        onSuccess: fermerEdition,
    });
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

const classeChamp = 'mt-1 block w-full min-h-[44px]';
</script>

<template>
    <Head title="Espace propriétaire" />

    <div class="min-h-screen bg-neutral-50 dark:bg-neutral-900">
        <header class="bg-primary">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-4 sm:px-6 sm:py-5">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/15">
                        <Building2 class="h-5 w-5 text-white" stroke-width="2.25" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-bold leading-tight text-white">Espace propriétaire</p>
                        <p class="truncate text-xs leading-tight text-primary-100/80">Gestion des organisations clientes</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-4">
                    <ThemeToggle />
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        aria-label="Déconnexion"
                        class="flex min-h-[44px] min-w-[44px] items-center justify-center gap-1.5 rounded-lg px-3 text-sm font-medium text-primary-100/90 transition hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    >
                        <LogOut class="h-4 w-4" aria-hidden="true" />
                        <span class="hidden sm:inline">Déconnexion</span>
                    </Link>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <div v-if="page.props.flash?.success" role="status" class="break-words rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success-700 ring-1 ring-success/20 [overflow-wrap:anywhere] dark:bg-success-900/20 dark:text-success-400 dark:ring-success-700/40">
                {{ page.props.flash.success }}
            </div>

            <!-- Carte d'identifiants après création -->
            <div
                v-if="page.props.flash?.credentials && identifiantsVisibles"
                class="relative overflow-hidden rounded-xl bg-primary p-4 pr-14 shadow-card sm:p-6 sm:pr-14"
            >
                <button
                    type="button"
                    class="absolute right-2 top-2 inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-100/70 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    aria-label="Masquer les identifiants"
                    @click="identifiantsVisibles = false"
                >
                    <X class="h-4 w-4" aria-hidden="true" />
                </button>
                <p class="break-words text-sm font-medium text-primary-100/80 [overflow-wrap:anywhere]">Nouveaux accès — « {{ page.props.flash.credentials.organisation }} »</p>
                <p class="mb-4 text-xs text-primary-100/60">Ces identifiants ne seront plus affichés après cette page. Transmets-les au client maintenant.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-white/10 py-1 pl-4 pr-1">
                        <div class="min-w-0">
                            <p class="text-xs text-primary-100/70">Email</p>
                            <p class="break-all font-mono text-sm text-white">{{ page.props.flash.credentials.email }}</p>
                        </div>
                        <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-primary-100/80 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="Copier l'e-mail" @click="copier(page.props.flash.credentials.email, 'email')">
                            <Check v-if="champCopie === 'email'" class="h-4 w-4" aria-hidden="true" />
                            <Copy v-else class="h-4 w-4" aria-hidden="true" />
                        </button>
                    </div>
                    <div class="flex min-w-0 items-center justify-between gap-2 rounded-lg bg-white/10 py-1 pl-4 pr-1">
                        <div class="min-w-0">
                            <p class="text-xs text-primary-100/70">Mot de passe temporaire</p>
                            <p class="break-all font-mono text-sm text-white">{{ page.props.flash.credentials.password }}</p>
                        </div>
                        <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-primary-100/80 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-white" aria-label="Copier le mot de passe" @click="copier(page.props.flash.credentials.password, 'password')">
                            <Check v-if="champCopie === 'password'" class="h-4 w-4" aria-hidden="true" />
                            <Copy v-else class="h-4 w-4" aria-hidden="true" />
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
                    <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-600 dark:text-neutral-400" aria-hidden="true" />
                    <input
                        v-model="recherche"
                        type="search"
                        aria-label="Rechercher une organisation"
                        placeholder="Rechercher une organisation…"
                        class="min-h-[44px] w-full rounded-lg border-neutral-300 py-2 pl-9 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500"
                    />
                </div>
                <PrimaryButton type="button" class="min-h-[44px] justify-center" @click="showCreateModal = true">
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nouvelle organisation
                </PrimaryButton>
            </div>

            <!-- Liste -->
            <DataTable
                :colonnes="colonnes"
                :lignes="organisationsFiltrees"
                legende="Organisations clientes et licences"
                :filtre-actif="Boolean(recherche)"
                :compteur="compteur"
                message-vide="Aucune organisation cliente pour le moment."
                :message-aucun-resultat="`Aucune organisation ne correspond à « ${recherche} ».`"
                largeur-actions="9rem"
            >
                <template #cellule-users_count="{ ligne }">
                    <span class="inline-flex items-center gap-1.5 text-neutral-600 dark:text-neutral-400">
                        <Users class="h-3.5 w-3.5" aria-hidden="true" />
                        {{ ligne.users_count }}
                    </span>
                </template>
                <template #cellule-statut="{ ligne }">
                    <Badge :variant="statut(ligne.license_expires_at).variant">
                        <ShieldCheck v-if="statut(ligne.license_expires_at).label === 'Active'" class="h-3 w-3" aria-hidden="true" />
                        {{ statut(ligne.license_expires_at).label }}
                    </Badge>
                </template>

                <template #actions="{ ligne }">
                    <SecondaryButton class="min-h-[44px]" :aria-label="`Modifier la licence de ${ligne.nom}`" @click="editer(ligne)">
                        <Pencil class="h-4 w-4" aria-hidden="true" />
                        Modifier
                    </SecondaryButton>
                </template>
            </DataTable>
        </main>

        <!-- Modification de la licence -->
        <Modal :show="enEdition !== null" max-width="lg" labelledby="titre-edition-licence" @close="fermerEdition">
            <form v-if="enEdition" class="space-y-4 p-6" @submit.prevent="enregistrer">
                <h2 id="titre-edition-licence" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                    Licence de « {{ enEdition.nom }} »
                </h2>
                <div>
                    <InputLabel for="edition-licence-nom" value="Nom de l'organisation" />
                    <TextInput id="edition-licence-nom" v-model="edition.nom" type="text" :class="classeChamp" required />
                    <InputError :message="edition.errors.nom" class="mt-1" />
                </div>
                <div>
                    <InputLabel for="edition-licence-date" value="Date d'expiration" />
                    <TextInput id="edition-licence-date" v-model="edition.license_expires_at" type="date" :class="classeChamp" aria-describedby="edition-licence-date-aide" />
                    <p id="edition-licence-date-aide" class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">Laisser vide pour une licence illimitée.</p>
                    <InputError :message="edition.errors.license_expires_at" class="mt-1" />
                </div>
                <div class="flex flex-wrap justify-end gap-3 pt-2">
                    <SecondaryButton class="min-h-[44px]" @click="fermerEdition">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="edition.processing || !edition.isDirty">Enregistrer</PrimaryButton>
                </div>
            </form>
        </Modal>

        <!-- Modal de création -->
        <Modal :show="showCreateModal" max-width="lg" labelledby="titre-creation-organisation" @close="showCreateModal = false">
            <form class="p-6" @submit.prevent="creerOrganisation">
                <h2 id="titre-creation-organisation" class="flex items-center gap-2 text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    <Building2 class="h-5 w-5 shrink-0 text-primary" aria-hidden="true" />
                    Nouvelle organisation cliente
                </h2>
                <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                    Un compte administrateur est créé automatiquement avec un mot de passe temporaire à transmettre au client.
                </p>

                <div class="mt-6 space-y-4">
                    <div>
                        <InputLabel for="nom" value="Nom de l'organisation" />
                        <TextInput id="nom" v-model="createForm.nom" type="text" :class="classeChamp" required autofocus />
                        <InputError :message="createForm.errors.nom" class="mt-1" />
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <InputLabel for="admin_nom" value="Nom de l'administrateur" />
                            <TextInput id="admin_nom" v-model="createForm.admin_nom" type="text" :class="classeChamp" required />
                            <InputError :message="createForm.errors.admin_nom" class="mt-1" />
                        </div>
                        <div>
                            <InputLabel for="admin_email" value="Email de l'administrateur" />
                            <TextInput id="admin_email" v-model="createForm.admin_email" type="email" :class="classeChamp" required />
                            <InputError :message="createForm.errors.admin_email" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <InputLabel for="license_expires_at" value="Date d'expiration (optionnel — laisser vide pour une licence illimitée)" />
                        <TextInput id="license_expires_at" v-model="createForm.license_expires_at" type="date" :class="classeChamp" />
                        <InputError :message="createForm.errors.license_expires_at" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <SecondaryButton class="min-h-[44px]" @click="showCreateModal = false">Annuler</SecondaryButton>
                    <PrimaryButton class="min-h-[44px]" :disabled="createForm.processing">Créer l'organisation</PrimaryButton>
                </div>
            </form>
        </Modal>
    </div>
</template>
