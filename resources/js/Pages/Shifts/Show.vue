<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import EtapeToggle from '@/Components/EtapeToggle.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    shift: Object,
    positions: Array,
    servantsDisponibles: Array,
    postesDisponibles: Array,
});

const optionsServants = computed(() => props.servantsDisponibles.map((s) => ({ value: s.id, label: s.nom_complet })));

const { confirmer } = useConfirm();

const retirerServant = async (positionId, assignmentId) => {
    if (!(await confirmer('Retirer ce servant du rôle ?', { danger: true }))) return;
    router.delete(route('shifts.positions.unassign', [props.shift.id, positionId, assignmentId]), {
        preserveScroll: true,
    });
};

const showAddPositionForm = ref(false);
const postesTableRef = ref(null);

// 'existant' : un servant déjà enregistré (recherche instantanée) ;
// 'nouveau' : créé à la volée avec ce rôle, sans passer par la page Servants.
const modeServant = ref('existant');

const form = useForm({
    shift_template_position_id: '',
    servant_id: '',
    nouveau_servant: {
        nom: '',
        prenom: '',
        genre: '',
        telephone: '',
    },
});

// Le nouveau titulaire est occupé, donc toujours ajouté en fin de tableau (cf.
// tri occupés/vacants côté serveur) : sans ça, rien ne signale qu'il a bien
// été créé tant qu'on n'a pas fait défiler la page jusqu'en bas.
const scrollerVersDernierPoste = () => {
    const lignes = postesTableRef.value?.querySelectorAll('tbody tr');
    const derniere = lignes?.[lignes.length - 1];
    derniere?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const ajouterServant = () => {
    form.transform((data) => ({
        shift_template_position_id: data.shift_template_position_id,
        ...(modeServant.value === 'nouveau'
            ? { nouveau_servant: data.nouveau_servant }
            : { servant_id: data.servant_id }),
    })).post(route('shifts.positions.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            modeServant.value = 'existant';
            showAddPositionForm.value = false;
            nextTick(scrollerVersDernierPoste);
        },
    });
};

const supprimerPoste = async (positionId) => {
    if (!(await confirmer('Supprimer ce rôle ?', { danger: true }))) return;
    router.delete(route('shifts.positions.destroy', [props.shift.id, positionId]), {
        preserveScroll: true,
    });
};

</script>

<template>
    <Head :title="`Shift : ${shift.nom}`" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Shifts', href: route('shifts.index') }, { label: shift.nom }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    {{ shift.nom }}
                </h2>
                <Link :href="route('shifts.edit', shift.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier le Shift
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <Link :href="route('shifts.index')" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Jour</dt>
                        <dd class="text-neutral-900 dark:text-neutral-100">{{ shift.jour }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600 dark:text-neutral-400">Horaire</dt>
                        <dd class="text-neutral-900 dark:text-neutral-100">{{ shift.heure_debut }} - {{ shift.heure_fin }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900 dark:text-neutral-100">Rôles du Shift</h3>
                    <PrimaryButton v-if="postesDisponibles.length > 0" @click="showAddPositionForm = !showAddPositionForm">
                        + Ajouter un servant
                    </PrimaryButton>
                </div>

                <form v-if="showAddPositionForm" @submit.prevent="ajouterServant" class="mb-6 space-y-4 rounded-md border border-dashed border-neutral-200 p-4 dark:border-neutral-600">
                    <div>
                        <InputLabel for="shift_template_position_id" value="Rôle" />
                        <select
                            id="shift_template_position_id"
                            v-model="form.shift_template_position_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="p in postesDisponibles" :key="p.id" :value="p.id">{{ p.nom }}</option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.shift_template_position_id" />
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-sm font-medium"
                            :class="modeServant === 'existant' ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700'"
                            @click="modeServant = 'existant'"
                        >
                            Servant existant
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-3 py-1 text-sm font-medium"
                            :class="modeServant === 'nouveau' ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700'"
                            @click="modeServant = 'nouveau'"
                        >
                            Nouveau servant
                        </button>
                    </div>

                    <div v-if="modeServant === 'existant'">
                        <InputLabel for="servant_id" value="Servant" />
                        <SearchableSelect
                            id="servant_id"
                            v-model="form.servant_id"
                            :options="optionsServants"
                            placeholder="Rechercher un servant…"
                            class="mt-1"
                        />
                        <InputError class="mt-1" :message="form.errors.servant_id" />
                    </div>
                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <InputLabel for="nouveau_prenom" value="Prénom" />
                            <TextInput id="nouveau_prenom" v-model="form.nouveau_servant.prenom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors['nouveau_servant.prenom']" />
                        </div>
                        <div>
                            <InputLabel for="nouveau_nom" value="Nom" />
                            <TextInput id="nouveau_nom" v-model="form.nouveau_servant.nom" type="text" class="mt-1 block w-full" required />
                            <InputError class="mt-1" :message="form.errors['nouveau_servant.nom']" />
                        </div>
                        <div>
                            <InputLabel for="nouveau_genre" value="Genre" />
                            <select
                                id="nouveau_genre"
                                v-model="form.nouveau_servant.genre"
                                class="mt-1 block w-full rounded-md border-neutral-300 dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 text-sm shadow-sm"
                            >
                                <option value="">Non précisé</option>
                                <option value="homme">Homme</option>
                                <option value="femme">Femme</option>
                            </select>
                            <InputError class="mt-1" :message="form.errors['nouveau_servant.genre']" />
                        </div>
                        <div>
                            <InputLabel for="nouveau_telephone" value="Téléphone (optionnel)" />
                            <TextInput id="nouveau_telephone" v-model="form.nouveau_servant.telephone" type="text" class="mt-1 block w-full" />
                            <InputError class="mt-1" :message="form.errors['nouveau_servant.telephone']" />
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>

                <p v-if="positions.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
                    Aucun rôle pour ce Shift pour le moment.
                </p>

                <div v-else class="overflow-x-auto">
                    <table ref="postesTableRef" class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Rôle</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Titulaire</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Coordonnées</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Appel</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Protection de l'enfance</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Badge</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Photo</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
                            <tr v-for="position in positions" :key="position.id">
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ position.nom }}</td>
                                <template v-if="position.titulaire">
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-900 dark:text-neutral-100">{{ position.titulaire.nom_complet }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">{{ position.titulaire.coordonnees ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600 dark:text-neutral-400">{{ position.titulaire.titre_leadership ?? '—' }}</td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <EtapeToggle
                                            :servant-id="position.titulaire.id"
                                            :workflow-step-id="position.titulaire.etapes.protection_jeunesse.workflow_step_id"
                                            :termine="position.titulaire.etapes.protection_jeunesse.termine"
                                        />
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <EtapeToggle
                                            :servant-id="position.titulaire.id"
                                            :workflow-step-id="position.titulaire.etapes.badge.workflow_step_id"
                                            :termine="position.titulaire.etapes.badge.termine"
                                        />
                                    </td>
                                    <td class="px-3 py-2.5 text-sm">
                                        <EtapeToggle
                                            :servant-id="position.titulaire.id"
                                            :workflow-step-id="position.titulaire.etapes.photo.workflow_step_id"
                                            :termine="position.titulaire.etapes.photo.termine"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right text-sm">
                                        <DangerButton @click="retirerServant(position.id, position.assignment_id)">Retirer</DangerButton>
                                    </td>
                                </template>
                                <template v-else>
                                    <td colspan="7" class="px-3 py-2.5 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-warning">Rôle vacant</span>
                                            <DangerButton @click="supprimerPoste(position.id)">Supprimer</DangerButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
