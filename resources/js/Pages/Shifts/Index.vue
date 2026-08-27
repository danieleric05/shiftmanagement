<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Badge from '@/Components/Badge.vue';
import { useTableSearch } from '@/composables/useTableSearch';
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
const shiftsFiltres = computed(() => shiftsCherches.value.filter((s) => !jourFiltre.value || s.jour === jourFiltre.value));

const shiftsFreres = computed(() => shiftsFiltres.value.filter((s) => s.genre === 'freres'));
const shiftsSoeurs = computed(() => shiftsFiltres.value.filter((s) => s.genre === 'soeurs'));
</script>

<template>
    <Head title="Gestion des Shifts" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                Gestion des Shifts
            </h2>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div v-if="shifts.length > 0" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <SearchInput v-model="recherche" placeholder="Rechercher un Shift…" />
                <select v-model="jourFiltre" class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                    <option value="">Tous les jours</option>
                    <option v-for="jour in joursDisponibles" :key="jour" :value="jour">{{ jourLabel(jour) }}</option>
                </select>
            </div>

            <div v-if="shifts.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Aucun Shift pour le moment.
            </div>
            <div v-else-if="shiftsFiltres.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Aucun Shift ne correspond à ces critères.
            </div>
            <div v-else class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600">Frères</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsFreres"
                                :key="shift.id"
                                :href="route('shifts.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50"
                            >
                                <span class="capitalize text-neutral-900">{{ shift.jour }} — {{ shift.nom }}</span>
                                <Badge v-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                <Badge v-else-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                                <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                            </Link>
                            <p v-if="shiftsFreres.length === 0" class="px-3 py-2 text-sm text-neutral-500">Aucun Shift.</p>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600">Sœurs</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsSoeurs"
                                :key="shift.id"
                                :href="route('shifts.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50"
                            >
                                <span class="capitalize text-neutral-900">{{ shift.jour }} — {{ shift.nom }}</span>
                                <Badge v-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                <Badge v-else-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                                <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                            </Link>
                            <p v-if="shiftsSoeurs.length === 0" class="px-3 py-2 text-sm text-neutral-500">Aucun Shift.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
