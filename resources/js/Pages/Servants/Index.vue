<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SortableHeader from '@/Components/SortableHeader.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useTableSort } from '@/composables/useTableSort';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { UserCheck, GraduationCap, UserPlus, UserX } from '@lucide/vue';

const props = defineProps({
    servants: Array,
    compteurs: Object,
});

const statutsDisponibles = [
    { value: 'recommande', label: 'Recommandé' },
    { value: 'en_formation', label: 'En formation' },
    { value: 'actif', label: 'Actif' },
    { value: 'suspendu', label: 'Relevé' },
    { value: 'retire', label: 'Retiré' },
];

const pieuxDisponibles = computed(() => [...new Set(props.servants.map((s) => s.pieu).filter(Boolean))].sort());

const { recherche, resultats: servantsCherches } = useTableSearch(() => props.servants, ['nom', 'prenom']);

const statutFiltre = ref('');
const pieuFiltre = ref('');
const servantsFiltresParColonne = computed(() => servantsCherches.value
    .filter((s) => !statutFiltre.value || s.statut === statutFiltre.value)
    .filter((s) => !pieuFiltre.value || s.pieu === pieuFiltre.value));

const { sortKey, sortDirection, toggleSort, sorted: servantsFiltres } = useTableSort(() => servantsFiltresParColonne.value);
</script>

<template>
    <Head title="Gestion des Servants" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Servants' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    Gestion des Servants
                </h2>
                <Link :href="route('servants.create')">
                    <PrimaryButton>+ Ajouter un Servant</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <StatCard label="Actifs" :value="compteurs.actifs" :icon="UserCheck" tone="primary" />
                <StatCard label="En formation" :value="compteurs.en_formation" :icon="GraduationCap" tone="primary" />
                <StatCard label="Recommandés" :value="compteurs.recommandes" :icon="UserPlus" tone="primary" />
                <StatCard label="Relevés" :value="compteurs.suspendus" :icon="UserX" tone="primary" />
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput v-model="recherche" placeholder="Rechercher un nom, un prénom…" />
                <select v-model="statutFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les statuts</option>
                    <option v-for="s in statutsDisponibles" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select v-model="pieuFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les pieux</option>
                    <option v-for="p in pieuxDisponibles" :key="p" :value="p">{{ p }}</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <SortableHeader label="Nom" sort-key="nom" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Prénom" sort-key="prenom" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Téléphone</th>
                                <SortableHeader label="Pieu" sort-key="pieu" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Statut" sort-key="statut" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            <tr v-if="servantsFiltres.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-neutral-600">
                                    <template v-if="recherche || statutFiltre || pieuFiltre">Aucun servant ne correspond à ces critères.</template>
                                    <template v-else>Aucun servant pour le moment.</template>
                                </td>
                            </tr>
                            <tr v-for="servant in servantsFiltres" :key="servant.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900">
                                    {{ servant.nom }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-900">
                                    {{ servant.prenom }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ servant.telephone ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ servant.pieu ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <StatusBadge :statut="servant.statut" domain="servant" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('servants.show', servant.id)" class="font-medium text-primary-light hover:text-primary">
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
