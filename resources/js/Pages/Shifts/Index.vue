<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SortableHeader from '@/Components/SortableHeader.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { useTableSort } from '@/composables/useTableSort';
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    shifts: Array,
});

const jourLabel = (jour) => jour.charAt(0).toUpperCase() + jour.slice(1);

const joursDisponibles = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche']
    .filter((jour) => props.shifts.some((s) => s.jour === jour));

const { recherche, resultats: shiftsCherches } = useTableSearch(() => props.shifts, ['nom']);

const jourFiltre = ref('');
const statutFiltre = ref('');
const shiftsFiltresParColonne = computed(() => shiftsCherches.value
    .filter((s) => !jourFiltre.value || s.jour === jourFiltre.value)
    .filter((s) => !statutFiltre.value || s.statut === statutFiltre.value));

const { sortKey, sortDirection, toggleSort, sorted: shiftsFiltres } = useTableSort(() => shiftsFiltresParColonne.value);
</script>

<template>
    <Head title="Gestion des Shifts" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Gestion des Shifts
            </h2>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <div v-if="shifts.length > 0" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" />
                <select v-model="jourFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les jours</option>
                    <option v-for="jour in joursDisponibles" :key="jour" :value="jour">{{ jourLabel(jour) }}</option>
                </select>
                <select v-model="statutFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les statuts</option>
                    <option value="actif">Actif</option>
                    <option value="inactif">Inactif</option>
                </select>
            </div>

            <div
                class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <SortableHeader label="Shift" sort-key="nom" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Horaire</th>
                                <SortableHeader label="Chef d'équipe" sort-key="chef_equipe" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Membres" sort-key="membres_count" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <SortableHeader label="Statut" sort-key="statut" :active-key="sortKey" :direction="sortDirection" @sort="toggleSort" />
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            <tr v-if="shifts.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-neutral-600">
                                    Aucun Shift pour le moment.
                                </td>
                            </tr>
                            <tr v-else-if="shiftsFiltres.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-neutral-600">
                                    Aucun Shift ne correspond à ces critères.
                                </td>
                            </tr>
                            <tr v-for="shift in shiftsFiltres" :key="shift.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900">
                                    {{ shift.nom }}<br />
                                    <span class="text-xs text-neutral-600">{{ jourLabel(shift.jour) }}</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ shift.heure_debut }} - {{ shift.heure_fin }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ shift.chef_equipe ?? '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ shift.membres_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <StatusBadge :statut="shift.statut" />
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('shifts.show', shift.id)" class="font-medium text-primary-light hover:text-primary">
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
