<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import StatCard from '@/Components/StatCard.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { CalendarClock, Gavel, UserRound, UsersRound } from '@lucide/vue';
import { useConfirm } from '@/composables/useConfirm';

defineProps({
    stats: Object,
    servants: Object,
    demandes: Object,
    shifts: Array,
    servantsDisponibles: Array,
});

const { confirmer } = useConfirm();

const positionForms = ref({});

const affecterServant = (shiftId, positionId) => {
    const servantId = positionForms.value[positionId];
    if (!servantId) return;

    router.post(route('shifts.positions.assign', [shiftId, positionId]), {
        servant_id: servantId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            positionForms.value[positionId] = '';
        },
    });
};

const retirerServant = async (shiftId, positionId, assignmentId) => {
    if (!(await confirmer('Retirer ce servant du poste ?', { danger: true }))) return;
    router.delete(route('shifts.positions.unassign', [shiftId, positionId, assignmentId]), {
        preserveScroll: true,
    });
};

const servantStats = [
    { key: 'actifs', label: 'Actifs' },
    { key: 'en_formation', label: 'En formation' },
    { key: 'recommandes', label: 'En attente' },
    { key: 'suspendus', label: 'Suspendus' },
];
</script>

<template>
    <Head title="Tableau de bord Administration" />

    <AuthenticatedLayout>
        <template #header>Tableau de bord Administration</template>

        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Vue d'ensemble -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Shifts actifs" :value="stats.shifts_actifs" :icon="CalendarClock" tone="primary" />
                <StatCard label="Membres actifs" :value="stats.membres_actifs" :icon="UsersRound" tone="primary" />
                <StatCard label="Avis en attente" :value="demandes.avis" :icon="Gavel" tone="warning" />
                <StatCard label="Demandes de retrait" :value="demandes.retraits" :icon="Gavel" tone="danger" />
            </div>

            <!-- Servants -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <UserRound class="h-5 w-5 text-primary" />
                        <h3 class="text-base font-semibold text-neutral-900">Servants</h3>
                    </div>
                    <Link :href="route('servants.index')" class="text-sm font-medium text-primary-light hover:text-primary">
                        Gérer les Servants →
                    </Link>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div v-for="item in servantStats" :key="item.key" class="rounded-lg bg-neutral-50 p-4 text-center">
                        <p class="text-3xl font-bold text-neutral-900">{{ servants[item.key] }}</p>
                        <p class="text-xs uppercase tracking-wide text-neutral-600">{{ item.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Shifts -->
            <div v-if="shifts.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Aucun Shift créé pour le moment.
            </div>

            <div v-for="shift in shifts" :key="shift.id" class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-semibold capitalize text-neutral-900">
                            {{ shift.jour }} — {{ shift.nom }}
                        </h3>
                        <div class="mt-1 flex items-center gap-2 text-sm text-neutral-600">
                            <span>Responsable : {{ shift.chef_equipe ?? '—' }}</span>
                            <template v-if="shift.postes_total > 0">
                                <Badge v-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                                <Badge v-else variant="warning">{{ shift.postes_vacants }} poste(s) vacant(s)</Badge>
                            </template>
                        </div>
                    </div>
                    <Link :href="route('shifts.show', shift.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                        Voir la fiche →
                    </Link>
                </div>

                <table v-if="shift.positions.length > 0" class="min-w-full divide-y divide-neutral-100">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Poste</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Titulaire</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="position in shift.positions" :key="position.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-900">
                                {{ position.nom }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm">
                                <span v-if="position.titulaire" class="text-neutral-600">
                                    {{ position.titulaire.nom_complet }} — depuis le {{ position.titulaire.depuis }}
                                </span>
                                <span v-else class="font-medium text-warning">
                                    ⚠ Personne manquante
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-right text-sm">
                                <DangerButton v-if="position.titulaire" @click="retirerServant(shift.id, position.id, position.assignment_id)">
                                    Retirer
                                </DangerButton>
                                <div v-else class="flex items-center justify-end gap-2">
                                    <select
                                        v-model="positionForms[position.id]"
                                        class="rounded-lg border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                                    >
                                        <option value="">Choisir un remplaçant</option>
                                        <option v-for="s in servantsDisponibles" :key="s.id" :value="s.id">
                                            {{ s.nom_complet }}
                                        </option>
                                    </select>
                                    <PrimaryButton @click="affecterServant(shift.id, position.id)">Affecter</PrimaryButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p v-else class="text-sm text-neutral-600">
                    Ce Shift n'a pas été créé à partir d'un modèle : aucun poste à afficher ici.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
