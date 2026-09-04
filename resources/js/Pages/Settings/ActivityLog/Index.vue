<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { History } from '@lucide/vue';

const props = defineProps({
    activites: Object,
    filtreRecherche: String,
});

const recherche = ref(props.filtreRecherche ?? '');

let rechercheTimeout = null;
const rechercher = (valeur) => {
    recherche.value = valeur;
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(() => {
        router.get(route('settings.activity-log.index'), valeur ? { recherche: valeur } : {}, { preserveState: true, replace: true });
    }, 300);
};

const evenementLabel = {
    created: 'Création',
    updated: 'Modification',
    deleted: 'Suppression',
    restored: 'Restauration',
};

const evenementVariant = {
    created: 'success',
    updated: 'info',
    deleted: 'danger',
    restored: 'warning',
};

const detailsOuverts = ref(null);

const basculerDetails = (id) => {
    detailsOuverts.value = detailsOuverts.value === id ? null : id;
};
</script>

<template>
    <Head title="Journal d'activité" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: `Journal d'activité` }]">
        <template #header>
            <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                <History class="h-5 w-5 text-primary" />
                Journal d'activité
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Historique des créations, modifications et suppressions sur les servants, shifts,
                relèves/permutations, candidats et entretiens de votre organisation.
            </p>

            <SearchInput :model-value="recherche" placeholder="Rechercher par auteur…" @update:model-value="rechercher" />

            <div v-if="activites.data.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <template v-if="filtreRecherche">Aucune activité ne correspond à « {{ filtreRecherche }} ».</template>
                <template v-else>Aucune activité enregistrée pour l'instant.</template>
            </div>

            <div v-else class="overflow-hidden rounded-xl bg-white dark:bg-neutral-800 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <thead class="bg-neutral-50 dark:bg-neutral-900">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Sur quoi</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400">Par qui</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700 bg-white dark:bg-neutral-800">
                            <template v-for="activite in activites.data" :key="activite.id">
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">{{ activite.date }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <Badge :variant="evenementVariant[activite.evenement] ?? 'neutral'">
                                            {{ evenementLabel[activite.evenement] ?? activite.evenement }}
                                        </Badge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900 dark:text-neutral-100">
                                        {{ activite.modele }} #{{ activite.sujet_id }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600 dark:text-neutral-400">{{ activite.causeur }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        <button
                                            type="button"
                                            class="font-medium text-primary-light hover:text-primary"
                                            @click="basculerDetails(activite.id)"
                                        >
                                            {{ detailsOuverts === activite.id ? 'Masquer' : 'Détails' }}
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="detailsOuverts === activite.id">
                                    <td colspan="5" class="bg-neutral-50 dark:bg-neutral-900 px-6 py-4">
                                        <pre class="overflow-x-auto whitespace-pre-wrap break-all text-xs text-neutral-700 dark:text-neutral-200">{{ JSON.stringify(activite.proprietes, null, 2) }}</pre>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="activites.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in activites.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
