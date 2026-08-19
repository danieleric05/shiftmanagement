<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    shifts: Array,
    releves: Object,
    permutations: Object,
    besoins: Object,
    entretiens: Array,
});

const today = () => new Date().toISOString().slice(0, 10);

const transfertForms = reactive({});
const formeTransfert = (id) => {
    if (!transfertForms[id]) {
        transfertForms[id] = { resultat: '', resultat_date: today() };
    }
    return transfertForms[id];
};

const resoudreTransfert = (id) => {
    const data = formeTransfert(id);
    router.patch(route('shift-transfers.resolve', id), data, { preserveScroll: true });
};

const entretienForms = reactive({});
const formeEntretien = (id) => {
    if (!entretienForms[id]) {
        entretienForms[id] = { resultat: '', valide: false, shift_affecte_id: '' };
    }
    return entretienForms[id];
};

const resoudreEntretien = (id) => {
    const data = formeEntretien(id);
    router.patch(route('interviews.resolve', id), {
        resultat: data.resultat,
        valide: data.valide,
        shift_affecte_id: data.valide ? (data.shift_affecte_id || null) : null,
    }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Tableau de bord" />

    <AuthenticatedLayout>
        <template #header>Tableau de bord des dirigeants</template>

        <div class="mx-auto max-w-6xl space-y-6">
            <!-- Shifts : accès direct aux fiches -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="text-base font-semibold text-neutral-900">Shifts</h3>
                <p class="mb-4 text-sm text-neutral-600">Cliquez pour accéder à la fiche du shift.</p>
                <div v-if="shifts.length === 0" class="text-sm text-neutral-600">
                    Aucun Shift créé pour le moment.
                </div>
                <div v-else class="grid grid-cols-1 gap-x-8 gap-y-1 sm:grid-cols-2">
                    <Link
                        v-for="shift in shifts"
                        :key="shift.id"
                        :href="route('shifts.show', shift.id)"
                        class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-neutral-50"
                    >
                        <span class="capitalize text-neutral-900">{{ shift.jour }} — {{ shift.nom }}</span>
                        <Badge v-if="shift.postes_total === 0" variant="neutral">—</Badge>
                        <Badge v-else-if="shift.postes_vacants === 0" variant="success">Complet</Badge>
                        <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                    </Link>
                </div>
            </div>

            <h2 class="text-lg font-semibold text-neutral-900">Résumé des actions des servants</h2>

            <!-- Demandes de relève -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Demandes de relève des servants
                        <span v-if="releves.en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ releves.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index', { type: 'releve' })" class="text-sm font-medium text-primary-light hover:text-primary">
                        Afficher tout →
                    </Link>
                </div>
                <p v-if="releves.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Shift</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Coordonnées</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Raison</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Discussion</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Résultat / Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="d in releves.recentes" :key="d.id">
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.shift }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-900">{{ d.servant }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.coordonnees ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.motif }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm text-neutral-600">{{ d.date_demande }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.discussion_servant ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm">
                                    <div v-if="d.statut === 'traitee'">
                                        <Badge variant="success">{{ d.resultat }}</Badge>
                                        <p class="mt-1 text-xs text-neutral-500">{{ d.resultat_date }}</p>
                                    </div>
                                    <form v-else @submit.prevent="resoudreTransfert(d.id)" class="flex flex-col gap-1">
                                        <TextInput v-model="formeTransfert(d.id).resultat" placeholder="Résultat" class="w-32 text-xs" required />
                                        <TextInput v-model="formeTransfert(d.id).resultat_date" type="date" class="w-32 text-xs" required />
                                        <PrimaryButton type="submit" class="text-xs">Valider</PrimaryButton>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Demandes de permutation -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">
                        Demandes de permutation des servants
                        <span v-if="permutations.en_attente > 0" class="ml-1 text-sm font-normal text-warning">
                            ({{ permutations.en_attente }} en attente)
                        </span>
                    </h3>
                    <Link :href="route('shift-transfers.index', { type: 'permutation' })" class="text-sm font-medium text-primary-light hover:text-primary">
                        Afficher tout →
                    </Link>
                </div>
                <p v-if="permutations.recentes.length === 0" class="text-sm text-neutral-600">
                    Aucune demande sur les 2 dernières semaines.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Shift</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Shift de/à</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Raison</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Approuvé 2 shifts</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Résultat / Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="d in permutations.recentes" :key="d.id">
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.shift }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-900">{{ d.servant }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.shift }} → {{ d.shift_destination }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ d.motif }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm text-neutral-600">{{ d.date_demande }}</td>
                                <td class="px-3 py-2.5 text-sm">
                                    <Badge :variant="d.approuve_deux_shifts ? 'success' : 'warning'">
                                        {{ d.approuve_deux_shifts ? 'Oui' : 'Non' }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2.5 text-sm">
                                    <div v-if="d.statut === 'traitee'">
                                        <Badge variant="success">{{ d.resultat }}</Badge>
                                        <p class="mt-1 text-xs text-neutral-500">{{ d.resultat_date }}</p>
                                    </div>
                                    <form v-else @submit.prevent="resoudreTransfert(d.id)" class="flex flex-col gap-1">
                                        <TextInput v-model="formeTransfert(d.id).resultat" placeholder="Résultat" class="w-32 text-xs" required />
                                        <TextInput v-model="formeTransfert(d.id).resultat_date" type="date" class="w-32 text-xs" required />
                                        <PrimaryButton type="submit" class="text-xs">Valider</PrimaryButton>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Besoins des nouveaux servants -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Besoins des nouveaux servants (2 prochains mois)</h3>
                    <Link :href="route('recruitment.index')" class="text-sm font-medium text-primary-light hover:text-primary">
                        Détails →
                    </Link>
                </div>
                <div class="grid grid-cols-2 gap-4 sm:w-1/2">
                    <div class="rounded-lg bg-neutral-50 p-4 text-center">
                        <p class="text-3xl font-bold text-neutral-900">{{ besoins.soeurs_recherchees }}</p>
                        <p class="text-xs uppercase tracking-wide text-neutral-600">Sœurs recherchées</p>
                    </div>
                    <div class="rounded-lg bg-neutral-50 p-4 text-center">
                        <p class="text-3xl font-bold text-neutral-900">{{ besoins.freres_recherches }}</p>
                        <p class="text-xs uppercase tracking-wide text-neutral-600">Frères recherchés</p>
                    </div>
                </div>
            </div>

            <!-- Entretiens programmés -->
            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-base font-semibold text-neutral-900">Entretiens programmés</h3>
                    <Link :href="route('interviews.index')" class="text-sm font-medium text-primary-light hover:text-primary">
                        Détails →
                    </Link>
                </div>
                <p v-if="entretiens.length === 0" class="text-sm text-neutral-600">
                    Aucun entretien programmé.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Nom</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Date</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">H</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Shift souhaité</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Lu / vu / engagé</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Résultat / affectation</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="e in entretiens" :key="e.id">
                                <td class="px-3 py-2.5 text-sm text-neutral-900">{{ e.candidat }}</td>
                                <td class="px-3 py-2.5 whitespace-nowrap text-sm text-neutral-600">{{ e.date_entretien }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ e.heure_entretien ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm text-neutral-600">{{ e.shift_souhaite ?? '—' }}</td>
                                <td class="px-3 py-2.5 text-sm">
                                    <Badge :variant="e.engagement_vu ? 'success' : 'neutral'">{{ e.engagement_vu ? 'Oui' : 'Non' }}</Badge>
                                </td>
                                <td class="px-3 py-2.5 text-sm">
                                    <form @submit.prevent="resoudreEntretien(e.id)" class="flex flex-col gap-1">
                                        <TextInput v-model="formeEntretien(e.id).resultat" placeholder="Résultat" class="w-40 text-xs" required />
                                        <label class="flex items-center gap-1 text-xs text-neutral-600">
                                            <input type="checkbox" v-model="formeEntretien(e.id).valide" class="rounded border-neutral-300 text-primary focus:ring-primary-light" />
                                            Candidat retenu
                                        </label>
                                        <select
                                            v-if="formeEntretien(e.id).valide"
                                            v-model="formeEntretien(e.id).shift_affecte_id"
                                            class="w-40 rounded-md border-neutral-300 text-xs shadow-sm focus:border-primary-light focus:ring-primary-light"
                                            required
                                        >
                                            <option value="">Shift d'affectation</option>
                                            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                                        </select>
                                        <PrimaryButton type="submit" class="text-xs">Valider</PrimaryButton>
                                    </form>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
