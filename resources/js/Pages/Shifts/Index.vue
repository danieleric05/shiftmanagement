<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    shifts: Array,
});

const { recherche, resultats: shiftsFiltres } = useTableSearch(() => props.shifts, ['nom']);

const jourLabel = (jour) => jour.charAt(0).toUpperCase() + jour.slice(1);
</script>

<template>
    <Head title="Gestion des Shifts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-neutral-900"
                >
                    Gestion des Shifts
                </h2>
                <Link :href="route('shifts.create')">
                    <PrimaryButton>+ Créer un Shift</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-6">
            <SearchInput v-if="shifts.length > 0" v-model="recherche" placeholder="Rechercher un Shift…" />

            <div
                class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Shift</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Horaire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Chef d'équipe</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Membres</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Statut</th>
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
                                    Aucun Shift ne correspond à « {{ recherche }} ».
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
