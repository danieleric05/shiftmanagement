<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    shifts: Array,
    releves: Object,
    permutations: Object,
    appels: Object,
    besoins: Object,
    entretiens: Array,
});

const page = usePage();

const shiftsFreres = computed(() => props.shifts.filter((shift) => shift.genre === 'freres'));
const shiftsSoeurs = computed(() => props.shifts.filter((shift) => shift.genre === 'soeurs'));
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>Bonjour, {{ page.props.auth.user.name }} 👋</template>

        <div class="mx-auto max-w-5xl space-y-6">
            <!-- Shifts : les miens en gestion, les autres en lecture seule -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="text-base font-semibold text-neutral-900">Shifts</h3>
                <p class="mb-4 text-sm text-neutral-600">Cliquez pour accéder à la fiche du shift — lecture seule pour les shifts que vous ne gérez pas.</p>
                <div v-if="shifts.length === 0" class="text-sm text-neutral-600">
                    Aucun Shift créé pour le moment.
                </div>
                <div v-else class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600">Frères</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsFreres"
                                :key="shift.id"
                                :href="route('shifts.mine.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50"
                            >
                                <span class="capitalize text-neutral-900">{{ shift.jour }} — {{ shift.nom }}</span>
                                <span class="flex items-center gap-2">
                                    <Badge v-if="!shift.gere" variant="neutral">Lecture seule</Badge>
                                    <Badge v-else-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                    <Badge v-else-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                                    <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                                </span>
                            </Link>
                        </div>
                    </div>
                    <div>
                        <h4 class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600">Sœurs</h4>
                        <div class="space-y-1">
                            <Link
                                v-for="shift in shiftsSoeurs"
                                :key="shift.id"
                                :href="route('shifts.mine.show', shift.id)"
                                class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50"
                            >
                                <span class="capitalize text-neutral-900">{{ shift.jour }} — {{ shift.nom }}</span>
                                <span class="flex items-center gap-2">
                                    <Badge v-if="!shift.gere" variant="neutral">Lecture seule</Badge>
                                    <Badge v-else-if="shift.postes_total === 0" variant="neutral">—</Badge>
                                    <Badge v-else-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                                    <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="text-lg font-semibold text-neutral-900">Résumé des actions des servants</h2>

            <!-- Demandes de relève -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Demandes de relève
                        <span v-if="releves.en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ releves.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index', { type: 'releve' })" class="text-sm font-medium text-success-700 hover:underline">
                        Voir / créer une demande →
                    </Link>
                </div>
                <p v-if="releves.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </p>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="d in releves.recentes" :key="d.id" class="flex justify-between">
                        <span class="text-neutral-900">{{ d.servant }} ({{ d.shift }})</span>
                        <span class="text-neutral-600">
                            <template v-if="d.statut === 'traitee'">{{ d.resultat }} — {{ d.resultat_date }}</template>
                            <template v-else>En attente</template>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Demandes de permutation -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Demandes de permutation
                        <span v-if="permutations.en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ permutations.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index', { type: 'permutation' })" class="text-sm font-medium text-success-700 hover:underline">
                        Voir / créer une demande →
                    </Link>
                </div>
                <p v-if="permutations.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </p>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="d in permutations.recentes" :key="d.id" class="flex justify-between">
                        <span class="text-neutral-900">{{ d.servant }} ({{ d.shift }} → {{ d.shift_destination }})</span>
                        <span class="text-neutral-600">
                            <template v-if="d.statut === 'traitee'">{{ d.resultat }} — {{ d.resultat_date }}</template>
                            <template v-else>En attente</template>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Demandes d'appel -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Demandes d'appel
                        <span v-if="appels.en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ appels.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index', { type: 'appel' })" class="text-sm font-medium text-success-700 hover:underline">
                        Voir / créer une demande →
                    </Link>
                </div>
                <p v-if="appels.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </p>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="d in appels.recentes" :key="d.id" class="flex justify-between">
                        <span class="text-neutral-900">{{ d.servant }} ({{ d.shift }})</span>
                        <span class="text-neutral-600">
                            <template v-if="d.statut === 'traitee'">{{ d.resultat }} — {{ d.resultat_date }}</template>
                            <template v-else>En attente</template>
                        </span>
                    </li>
                </ul>
            </div>

            <!-- Recrutement & entretiens -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Besoins des nouveaux servants</h3>
                    <div class="flex gap-3 text-sm font-medium text-success-700">
                        <Link :href="route('recruitment.index')" class="hover:underline">Besoins →</Link>
                        <Link :href="route('candidates.index')" class="hover:underline">Candidats →</Link>
                        <Link :href="route('interviews.index')" class="hover:underline">Entretiens →</Link>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:w-1/2">
                    <div class="rounded-lg bg-success-50 p-4 text-center">
                        <p class="text-3xl font-bold text-success-700">{{ besoins.soeurs_recherchees }}</p>
                        <p class="text-xs uppercase tracking-wide text-success-700/80">Sœurs recherchées</p>
                    </div>
                    <div class="rounded-lg bg-success-50 p-4 text-center">
                        <p class="text-3xl font-bold text-success-700">{{ besoins.freres_recherches }}</p>
                        <p class="text-xs uppercase tracking-wide text-success-700/80">Frères recherchés</p>
                    </div>
                </div>
            </div>

            <!-- Entretiens à venir -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Entretiens à venir</h3>
                </div>
                <p v-if="entretiens.length === 0" class="text-sm text-neutral-600">
                    Aucun entretien à venir.
                </p>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="e in entretiens" :key="e.id" class="flex justify-between">
                        <span class="text-neutral-900">{{ e.candidat }} — {{ e.shift_souhaite }}</span>
                        <span class="text-neutral-600">{{ e.date_entretien }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
