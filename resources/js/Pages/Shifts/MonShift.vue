<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    shift: Object,
    membres: Array,
    positions: Array,
});
</script>

<template>
    <Head :title="shift.nom" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold capitalize leading-tight text-neutral-900">
                    {{ shift.jour }} — {{ shift.nom }}
                </h2>
                <Link :href="route('dashboard')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <p class="text-sm text-neutral-600">
                {{ shift.heure_debut }} – {{ shift.heure_fin }} · Consultation seule — pour toute modification, utilisez les demandes de relève/permutation ou contactez l'administrateur.
            </p>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-base font-semibold text-neutral-900">Équipe de coordination</h3>
                <div v-if="membres.length === 0" class="text-sm text-neutral-600">Aucun membre affecté.</div>
                <table v-else class="min-w-full divide-y divide-neutral-100">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Rôle</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Depuis</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="(m, i) in membres" :key="i">
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-900">{{ m.name }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-600">{{ m.role }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-600">{{ m.date_debut }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-base font-semibold text-neutral-900">Postes &amp; servants affectés</h3>
                <div v-if="positions.length === 0" class="text-sm text-neutral-600">Aucun poste défini pour ce shift.</div>
                <table v-else class="min-w-full divide-y divide-neutral-100">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Poste</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Titulaire</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        <tr v-for="position in positions" :key="position.id">
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm text-neutral-900">{{ position.nom }}</td>
                            <td class="whitespace-nowrap px-4 py-2.5 text-sm">
                                <span v-if="position.titulaire" class="text-neutral-600">
                                    {{ position.titulaire.nom_complet }} — depuis le {{ position.titulaire.depuis }}
                                </span>
                                <span v-else class="font-medium text-warning">⚠ Personne manquante</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
