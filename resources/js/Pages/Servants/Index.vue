<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatCard from '@/Components/StatCard.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, Link } from '@inertiajs/vue3';
import { UserCheck, GraduationCap, UserPlus, UserX } from '@lucide/vue';

const props = defineProps({
    servants: Array,
    compteurs: Object,
});

const { recherche, resultats: servantsFiltres } = useTableSearch(() => props.servants, ['nom', 'prenom']);
</script>

<template>
    <Head title="Gestion des Servants" />

    <AuthenticatedLayout>
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
                <StatCard label="Suspendus" :value="compteurs.suspendus" :icon="UserX" tone="primary" />
            </div>

            <SearchInput v-model="recherche" placeholder="Rechercher un nom, un prénom…" />

            <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Prénom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Téléphone</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Pieu</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Statut</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            <tr v-if="servantsFiltres.length === 0">
                                <td colspan="6" class="px-6 py-8 text-center text-neutral-600">
                                    <template v-if="recherche">Aucun servant ne correspond à « {{ recherche }} ».</template>
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
                                    <StatusBadge :statut="servant.statut" />
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
