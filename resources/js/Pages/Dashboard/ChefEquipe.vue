<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CalendarClock, MessageCircle, MessageCircleQuestion, UserPlus, UsersRound } from '@lucide/vue';

const props = defineProps({
    shifts: Array,
    transferts: Object,
    recrutement: Object,
    entretiensAVenir: Array,
});

const typeLabel = { releve: 'Relève', permutation: 'Permutation' };

const page = usePage();

const totalMembres = computed(() =>
    props.shifts.reduce((total, shift) => total + shift.membres.length, 0),
);
</script>

<template>
    <Head title="Mon Shift" />

    <AuthenticatedLayout>
        <template #header>Bonjour, {{ page.props.auth.user.name }} 👋</template>

        <div class="mx-auto max-w-4xl space-y-6">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Shifts gérés" :value="shifts.length" :icon="CalendarClock" tone="success" />
                <StatCard label="Membres au total" :value="totalMembres" :icon="UsersRound" tone="success" />
                <StatCard label="Besoins de recrutement" :value="recrutement.total_a_recruter" :icon="UserPlus" tone="success" />
                <StatCard label="Entretiens à venir" :value="entretiensAVenir.length" :icon="MessageCircleQuestion" tone="success" />
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Relèves &amp; permutations
                        <span v-if="transferts.releves_en_attente + transferts.permutations_en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ transferts.releves_en_attente + transferts.permutations_en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index')" class="text-sm font-medium text-success-700 hover:underline">
                        Voir / créer une demande →
                    </Link>
                </div>
                <div v-if="transferts.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </div>
                <ul v-else class="space-y-1 text-sm">
                    <li v-for="d in transferts.recentes" :key="d.id" class="flex justify-between">
                        <span class="text-neutral-900">{{ typeLabel[d.type] }} — {{ d.servant }} ({{ d.shift }})</span>
                        <span class="text-neutral-600">{{ d.statut === 'traitee' ? 'Traitée' : 'En attente' }}</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Recrutement &amp; entretiens</h3>
                    <div class="flex gap-3 text-sm font-medium text-success-700">
                        <Link :href="route('recruitment.index')" class="hover:underline">Besoins →</Link>
                        <Link :href="route('candidates.index')" class="hover:underline">Candidats →</Link>
                        <Link :href="route('interviews.index')" class="hover:underline">Entretiens →</Link>
                    </div>
                </div>
                <p v-if="recrutement.shifts.length === 0 && entretiensAVenir.length === 0" class="text-sm text-neutral-600">
                    Aucun besoin de recrutement ni entretien programmé.
                </p>
                <div v-else class="space-y-1 text-sm">
                    <p v-for="s in recrutement.shifts" :key="s.shift" class="flex justify-between">
                        <span class="text-neutral-900">{{ s.shift }}</span>
                        <span class="text-neutral-600">{{ s.candidats_actifs }} / {{ s.nombre_a_recruter }} candidat(s) en cours</span>
                    </p>
                    <p v-for="e in entretiensAVenir" :key="e.id" class="flex justify-between">
                        <span class="text-neutral-900">Entretien — {{ e.candidat }}</span>
                        <span class="text-neutral-600">{{ e.date_entretien }}</span>
                    </p>
                </div>
            </div>

            <div v-if="shifts.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Vous n'êtes actuellement responsable d'aucun Shift.
            </div>

            <div
                v-for="shift in shifts"
                :key="shift.id"
                class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100"
            >
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <p class="text-base font-semibold capitalize text-neutral-900">
                            {{ shift.nom }} — {{ shift.jour }}
                        </p>
                        <p class="text-sm text-neutral-600">
                            {{ shift.heure_debut }} - {{ shift.heure_fin }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-success-50 px-4 py-2 text-center">
                            <p class="text-2xl font-bold text-success-700">{{ shift.membres.length }}</p>
                            <p class="text-xs uppercase tracking-wide text-success-700/80">Membres</p>
                        </div>
                        <Link :href="route('shifts.mine.show', shift.id)" class="text-sm font-medium text-success-700 hover:underline">
                            Fiche complète →
                        </Link>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Rôle</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="(m, i) in shift.membres" :key="i">
                                <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-900">{{ m.name }}</td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-600">{{ m.role }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-2 flex items-center gap-2">
                    <MessageCircle class="h-5 w-5 text-success-700" />
                    <h3 class="text-base font-semibold text-neutral-900">Communication et signalements</h3>
                </div>
                <p class="text-sm text-neutral-600">
                    L'envoi d'annonces et le signalement de difficultés arrivent dans une prochaine version (V3 de la roadmap).
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
