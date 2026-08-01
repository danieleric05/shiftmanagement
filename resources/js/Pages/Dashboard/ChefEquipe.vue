<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CalendarClock, MessageCircle, UsersRound } from '@lucide/vue';

const props = defineProps({
    shifts: Array,
});

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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <StatCard label="Shifts gérés" :value="shifts.length" :icon="CalendarClock" tone="success" />
                <StatCard label="Membres au total" :value="totalMembres" :icon="UsersRound" tone="success" />
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
                    <div class="rounded-lg bg-success-50 px-4 py-2 text-center">
                        <p class="text-2xl font-bold text-success-700">{{ shift.membres.length }}</p>
                        <p class="text-xs uppercase tracking-wide text-success-700/80">Membres</p>
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
