<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    shift: Object,
    membres: Array,
    positions: Array,
    estMonShift: Boolean,
});
</script>

<template>
    <Head :title="shift.nom" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-semibold capitalize leading-tight text-neutral-900">
                        {{ shift.jour }} — {{ shift.nom }}
                    </h2>
                    <Badge v-if="estMonShift" variant="success">Mon shift</Badge>
                    <Badge v-else variant="neutral">Lecture seule</Badge>
                </div>
                <Link :href="route('dashboard')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <p class="text-sm text-neutral-600">
                {{ shift.heure_debut }} – {{ shift.heure_fin }} ·
                <template v-if="estMonShift">
                    Pour toute modification, utilisez les
                    <Link :href="route('shift-transfers.index')" class="font-medium text-primary-light hover:text-primary">demandes de relève/permutation</Link>.
                </template>
                <template v-else>
                    Consultation seule — ce shift n'est pas géré par vous, contactez son coordinateur ou l'administrateur pour toute modification.
                </template>
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
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Poste</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Titulaire</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Coordonnées</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Leader</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Protection jeunesse</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Badge</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Photo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Orientation</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Formation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="position in positions" :key="position.id">
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900">{{ position.nom }}</td>
                                <template v-if="position.titulaire">
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-900">
                                        <Link
                                            :href="route('servants.mine.show', position.titulaire.id)"
                                            class="font-medium text-primary-light hover:text-primary"
                                        >
                                            {{ position.titulaire.nom_complet }}
                                        </Link>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600">{{ position.titulaire.coordonnees ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600">{{ position.titulaire.titre_leadership ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="position.titulaire.etapes.protection_jeunesse ? 'success' : 'neutral'">
                                            {{ position.titulaire.etapes.protection_jeunesse ? 'Oui' : 'Non' }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="position.titulaire.etapes.badge ? 'success' : 'neutral'">
                                            {{ position.titulaire.etapes.badge ? 'Oui' : 'Non' }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="position.titulaire.etapes.photo ? 'success' : 'neutral'">
                                            {{ position.titulaire.etapes.photo ? 'Oui' : 'Non' }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="position.titulaire.etapes.orientation ? 'success' : 'neutral'">
                                            {{ position.titulaire.etapes.orientation ? 'Oui' : 'Non' }}
                                        </Badge>
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <Badge :variant="position.titulaire.etapes.formation ? 'success' : 'neutral'">
                                            {{ position.titulaire.etapes.formation ? 'Oui' : 'Non' }}
                                        </Badge>
                                    </td>
                                </template>
                                <td v-else colspan="8" class="px-3 py-2.5 text-sm font-medium text-warning">⚠ Personne manquante</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
