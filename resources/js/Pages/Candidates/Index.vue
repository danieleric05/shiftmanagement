<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatCard from '@/Components/StatCard.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { CalendarClock, Phone, UserCheck, UserPlus } from '@lucide/vue';

const props = defineProps({
    candidats: Array,
    shifts: Array,
    estAdministrateur: Boolean,
    peutCreerCandidat: Boolean,
    compteurs: Object,
});

const { recherche, resultats: candidatsFiltres } = useTableSearch(() => props.candidats, ['nom_complet']);

const statuts = [
    { value: 'nouveau', label: 'Nouveau' },
    { value: 'appele', label: 'Appelé' },
    { value: 'entretien_planifie', label: 'Entretien planifié' },
    { value: 'entretien_realise', label: 'Entretien réalisé' },
    { value: 'converti', label: 'Converti' },
    { value: 'abandonne', label: 'Abandonné' },
];

const showCreateForm = ref(false);

const form = useForm({
    nom: '',
    prenom: '',
    telephone: '',
    shift_souhaite_id: '',
    date_appel: '',
    notes: '',
});

const creerCandidat = () => {
    form.post(route('candidates.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

const editForms = reactive(
    Object.fromEntries(
        props.candidats.map((c) => [
            c.id,
            useForm({
                telephone: c.telephone ?? '',
                date_appel: c.date_appel ?? '',
                statut: c.statut,
                notes: c.notes ?? '',
            }),
        ]),
    ),
);

const enregistrer = (id) => {
    editForms[id].patch(route('candidates.update', id), { preserveScroll: true });
};

const supprimer = (id) => {
    router.delete(route('candidates.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Candidats" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900">
                    <UserPlus class="h-5 w-5 text-primary" />
                    Candidats
                </h2>
                <PrimaryButton v-if="peutCreerCandidat" @click="showCreateForm = !showCreateForm">+ Nouveau candidat</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Candidats actifs" :value="compteurs.actifs" :icon="UserPlus" tone="primary" />
                <StatCard label="Convertis en servants" :value="compteurs.convertis" :icon="UserCheck" tone="success" />
            </div>

            <form v-if="showCreateForm" @submit.prevent="creerCandidat" class="grid grid-cols-1 gap-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 sm:grid-cols-3">
                <div>
                    <InputLabel for="nom" value="Nom" />
                    <input id="nom" v-model="form.nom" type="text" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required />
                    <InputError class="mt-2" :message="form.errors.nom" />
                </div>
                <div>
                    <InputLabel for="prenom" value="Prénom" />
                    <input id="prenom" v-model="form.prenom" type="text" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required />
                    <InputError class="mt-2" :message="form.errors.prenom" />
                </div>
                <div>
                    <InputLabel for="telephone" value="Téléphone" />
                    <input id="telephone" v-model="form.telephone" type="text" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" />
                    <InputError class="mt-2" :message="form.errors.telephone" />
                </div>
                <div>
                    <InputLabel for="shift_souhaite_id" value="Shift souhaité" />
                    <select id="shift_souhaite_id" v-model="form.shift_souhaite_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_souhaite_id" />
                </div>
                <div>
                    <InputLabel for="date_appel" value="Date d'appel" />
                    <input id="date_appel" v-model="form.date_appel" type="date" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" />
                    <InputError class="mt-2" :message="form.errors.date_appel" />
                </div>
                <div class="sm:col-span-3">
                    <InputLabel for="notes" value="Notes" />
                    <textarea id="notes" v-model="form.notes" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"></textarea>
                    <InputError class="mt-2" :message="form.errors.notes" />
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </div>
            </form>

            <SearchInput v-if="candidats.length > 0" v-model="recherche" placeholder="Rechercher un nom, un prénom…" />

            <div v-if="candidats.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                <template v-if="peutCreerCandidat">Aucun candidat enregistré pour l'instant. Ajoutez la première personne appelée pour un Shift avec « + Nouveau candidat ».</template>
                <template v-else>Aucun candidat enregistré pour l'instant.</template>
            </div>
            <div v-else-if="candidatsFiltres.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Aucun candidat ne correspond à « {{ recherche }} ».
            </div>

            <div v-for="c in candidatsFiltres" :key="c.id" class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="font-medium text-neutral-900">{{ c.nom_complet }}</div>
                        <div class="text-sm text-neutral-600">Shift souhaité : {{ c.shift_souhaite ?? '—' }}</div>
                        <div class="mt-1 flex items-center gap-3 text-xs text-neutral-500">
                            <span v-if="c.telephone" class="flex items-center gap-1"><Phone class="h-3.5 w-3.5" />{{ c.telephone }}</span>
                            <span v-if="c.date_appel" class="flex items-center gap-1"><CalendarClock class="h-3.5 w-3.5" />{{ c.date_appel }}</span>
                        </div>
                    </div>
                    <StatusBadge :statut="c.statut" />
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Téléphone</label>
                        <input v-model="editForms[c.id].telephone" type="text" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Date d'appel</label>
                        <input v-model="editForms[c.id].date_appel" type="date" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Statut</label>
                        <select v-model="editForms[c.id].statut" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                            <option v-for="s in statuts" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Notes</label>
                        <input v-model="editForms[c.id].notes" type="text" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end gap-2">
                    <DangerButton v-if="estAdministrateur" @click="supprimer(c.id)">Supprimer</DangerButton>
                    <SecondaryButton :disabled="editForms[c.id].processing" @click="enregistrer(c.id)">Enregistrer</SecondaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
