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
import { useConfirm } from '@/composables/useConfirm';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { CalendarClock, CheckCircle2, MessageCircleQuestion, UserRound } from '@lucide/vue';

const { confirmer } = useConfirm();

const props = defineProps({
    entretiens: Array,
    shifts: Array,
    candidats: Array,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const { recherche, resultats: entretiensFiltres } = useTableSearch(() => props.entretiens, ['candidat']);

const showCreateForm = ref(false);

const form = useForm({
    candidate_id: '',
    shift_souhaite_id: '',
    date_entretien: '',
    engagement_vu: false,
});

const planifier = () => {
    form.post(route('interviews.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

const resolveForms = reactive(
    Object.fromEntries(
        props.entretiens
            .filter((i) => i.statut === 'planifie')
            .map((i) => [
                i.id,
                useForm({
                    resultat: '',
                    valide: true,
                    shift_affecte_id: '',
                }),
            ]),
    ),
);

const resoudre = (id) => {
    resolveForms[id].patch(route('interviews.resolve', id), { preserveScroll: true });
};

const reprogrammerForms = reactive(
    Object.fromEntries(
        props.entretiens
            .filter((i) => i.statut === 'planifie')
            .map((i) => [
                i.id,
                useForm({
                    date_entretien: i.date_entretien,
                    engagement_vu: i.engagement_vu,
                }),
            ]),
    ),
);

const reprogrammer = (id) => {
    reprogrammerForms[id].patch(route('interviews.update', id), { preserveScroll: true });
};

const annuler = async (id) => {
    if (!(await confirmer('Annuler cet entretien ?', { danger: true }))) return;
    router.patch(route('interviews.cancel', id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Entretiens" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Entretiens' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    <MessageCircleQuestion class="h-5 w-5 text-primary" />
                    Entretiens
                </h2>
                <PrimaryButton @click="showCreateForm = !showCreateForm">+ Planifier un entretien</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Entretiens à venir" :value="compteurs.a_venir" :icon="CalendarClock" tone="warning" />
                <StatCard label="Entretiens réalisés" :value="compteurs.realises" :icon="CheckCircle2" tone="success" />
            </div>

            <form v-if="showCreateForm" @submit.prevent="planifier" class="grid grid-cols-1 gap-4 rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 sm:grid-cols-3">
                <div>
                    <InputLabel for="candidate_id" value="Candidat" />
                    <select id="candidate_id" v-model="form.candidate_id" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="c in candidats" :key="c.id" :value="c.id">{{ c.prenom }} {{ c.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.candidate_id" />
                </div>
                <div>
                    <InputLabel for="shift_souhaite_id" value="Shift souhaité" />
                    <select id="shift_souhaite_id" v-model="form.shift_souhaite_id" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_souhaite_id" />
                </div>
                <div>
                    <InputLabel for="date_entretien" value="Date" />
                    <input id="date_entretien" v-model="form.date_entretien" type="date" class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required />
                    <InputError class="mt-2" :message="form.errors.date_entretien" />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input id="engagement_vu" v-model="form.engagement_vu" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500" />
                    <InputLabel for="engagement_vu" value="Engagement vu" />
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Planifier</PrimaryButton>
                </div>
            </form>

            <SearchInput v-if="entretiens.length > 0" v-model="recherche" placeholder="Rechercher un candidat…" />

            <div v-if="entretiens.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Aucun entretien planifié pour l'instant. Utilisez « + Planifier un entretien » dès qu'un candidat est prêt à être reçu.
            </div>
            <div v-else-if="entretiensFiltres.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Aucun entretien ne correspond à « {{ recherche }} ».
            </div>

            <div v-for="i in entretiensFiltres" :key="i.id" class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700 transition hover:shadow-md">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-2 font-medium text-neutral-900 dark:text-neutral-100">
                            <UserRound class="h-4 w-4 text-primary" />
                            {{ i.candidat }}
                        </div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">
                            Shift souhaité : {{ i.shift_souhaite ?? '—' }} — {{ i.date_entretien }}
                        </div>
                        <div class="text-sm text-neutral-600 dark:text-neutral-400">Planifié par {{ i.planifie_par }}</div>
                        <div v-if="i.engagement_vu" class="text-xs text-success-700 dark:text-success-400">Engagement vu</div>
                    </div>
                    <StatusBadge :statut="i.statut" />
                </div>

                <div v-if="i.statut === 'realise'" class="mt-3 text-sm text-neutral-600 dark:text-neutral-400">
                    Résultat : {{ i.resultat }}
                    <span v-if="i.shift_affecte"> — affecté au Shift {{ i.shift_affecte }}</span>
                    <span v-if="i.decideur"> (décidé par {{ i.decideur }})</span>
                </div>

                <div v-if="i.statut === 'planifie'" class="mt-4 flex flex-wrap items-end gap-3 border-t border-neutral-100 dark:border-neutral-700 pt-4">
                    <div>
                        <InputLabel :for="`date-${i.id}`" value="Date" />
                        <input
                            :id="`date-${i.id}`"
                            v-model="reprogrammerForms[i.id].date_entretien"
                            type="date"
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        />
                        <InputError class="mt-1" :message="reprogrammerForms[i.id].errors.date_entretien" />
                    </div>
                    <label class="flex items-center gap-2 pb-2 text-sm text-neutral-700 dark:text-neutral-200">
                        <input v-model="reprogrammerForms[i.id].engagement_vu" type="checkbox" class="rounded border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500" />
                        Engagement vu
                    </label>
                    <SecondaryButton :disabled="reprogrammerForms[i.id].processing" @click="reprogrammer(i.id)">
                        Reprogrammer
                    </SecondaryButton>
                    <DangerButton type="button" @click="annuler(i.id)">Annuler le rendez-vous</DangerButton>
                </div>

                <div v-if="i.statut === 'planifie' && estAdministrateur" class="mt-4 space-y-3 border-t border-neutral-100 dark:border-neutral-700 pt-4">
                    <div>
                        <InputLabel :for="`resultat-${i.id}`" value="Résultat" />
                        <textarea
                            :id="`resultat-${i.id}`"
                            v-model="resolveForms[i.id].resultat"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        ></textarea>
                        <InputError class="mt-1" :message="resolveForms[i.id].errors.resultat" />
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                            <input type="radio" :name="`valide-${i.id}`" :value="true" v-model="resolveForms[i.id].valide" />
                            Validé
                        </label>
                        <label class="flex items-center gap-2 text-sm text-neutral-700 dark:text-neutral-200">
                            <input type="radio" :name="`valide-${i.id}`" :value="false" v-model="resolveForms[i.id].valide" />
                            Refusé
                        </label>
                    </div>
                    <div v-if="resolveForms[i.id].valide">
                        <InputLabel :for="`shift-${i.id}`" value="Shift d'affectation" />
                        <select
                            :id="`shift-${i.id}`"
                            v-model="resolveForms[i.id].shift_affecte_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                        </select>
                        <InputError class="mt-1" :message="resolveForms[i.id].errors.shift_affecte_id" />
                    </div>
                    <div class="flex justify-end">
                        <SecondaryButton :disabled="resolveForms[i.id].processing" @click="resoudre(i.id)">
                            Enregistrer le résultat
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
