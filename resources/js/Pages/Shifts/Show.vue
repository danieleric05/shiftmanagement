<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    shift: Object,
    membres: Array,
    membresDisponibles: Array,
    roles: Array,
    positions: Array,
    servantsDisponibles: Array,
});

const showAddForm = ref(false);

const form = useForm({
    user_id: '',
    role_id: '',
});

const addMember = () => {
    form.post(route('shifts.members.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showAddForm.value = false;
        },
    });
};

const removeMember = (affectationId) => {
    if (!confirm("Confirmer la fin de cette affectation ?")) return;
    router.delete(route('shifts.members.destroy', [props.shift.id, affectationId]), {
        preserveScroll: true,
    });
};

const positionForms = ref({});

const affecterServant = (positionId) => {
    const servantId = positionForms.value[positionId];
    if (!servantId) return;

    router.post(route('shifts.positions.assign', [props.shift.id, positionId]), {
        servant_id: servantId,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            positionForms.value[positionId] = '';
        },
    });
};

const retirerServant = (positionId, assignmentId) => {
    if (!confirm('Retirer ce servant du poste ?')) return;
    router.delete(route('shifts.positions.unassign', [props.shift.id, positionId, assignmentId]), {
        preserveScroll: true,
    });
};

const showAddPositionForm = ref(false);

const positionForm = useForm({
    nom: '',
});

const ajouterPoste = () => {
    positionForm.post(route('shifts.positions.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            positionForm.reset();
            showAddPositionForm.value = false;
        },
    });
};

const supprimerPoste = (positionId) => {
    if (!confirm('Supprimer ce poste de ce Shift ?')) return;
    router.delete(route('shifts.positions.destroy', [props.shift.id, positionId]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Shift : ${shift.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {{ shift.nom }}
                </h2>
                <Link :href="route('shifts.edit', shift.id)" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400">
                    Modifier le Shift
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Jour</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ shift.jour }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Horaire</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ shift.heure_debut }} - {{ shift.heure_fin }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Statut</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ shift.statut }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase text-gray-500 dark:text-gray-400">Membres actifs</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ membres.length }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Postes du Shift</h3>
                        <PrimaryButton @click="showAddPositionForm = !showAddPositionForm">
                            + Ajouter un poste
                        </PrimaryButton>
                    </div>

                    <form v-if="showAddPositionForm" @submit.prevent="ajouterPoste" class="mb-6 flex gap-3">
                        <TextInput
                            v-model="positionForm.nom"
                            type="text"
                            class="block w-full"
                            placeholder="Ex: Servant (poste supplémentaire)"
                            required
                        />
                        <PrimaryButton :disabled="positionForm.processing">Ajouter</PrimaryButton>
                    </form>
                    <InputError class="mb-4" :message="positionForm.errors.nom" />

                    <p v-if="positions.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                        Aucun poste pour ce Shift pour le moment.
                    </p>

                    <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="position in positions" :key="position.id" class="py-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="font-medium text-gray-900 dark:text-gray-100">{{ position.nom }}</div>
                                    <div v-if="position.titulaire" class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ position.titulaire.nom_complet }} — depuis le {{ position.titulaire.depuis }}
                                    </div>
                                    <div v-else class="text-sm text-amber-600 dark:text-amber-400">
                                        Poste vacant
                                    </div>
                                </div>

                                <DangerButton v-if="position.titulaire" @click="retirerServant(position.id, position.assignment_id)">
                                    Retirer
                                </DangerButton>
                                <div v-else class="flex items-center gap-2">
                                    <select
                                        v-model="positionForms[position.id]"
                                        class="rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    >
                                        <option value="">Sélectionner un servant</option>
                                        <option v-for="s in servantsDisponibles" :key="s.id" :value="s.id">
                                            {{ s.nom_complet }}
                                        </option>
                                    </select>
                                    <PrimaryButton @click="affecterServant(position.id)">Affecter</PrimaryButton>
                                    <SecondaryButton @click="supprimerPoste(position.id)">Supprimer</SecondaryButton>
                                </div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Membres affectés</h3>
                        <PrimaryButton @click="showAddForm = !showAddForm">
                            + Affecter un membre
                        </PrimaryButton>
                    </div>

                    <form v-if="showAddForm" @submit.prevent="addMember" class="mb-6 grid grid-cols-1 gap-4 rounded-md bg-gray-50 p-4 dark:bg-gray-900 sm:grid-cols-3">
                        <div>
                            <InputLabel for="user_id" value="Membre" />
                            <select
                                id="user_id"
                                v-model="form.user_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option v-for="u in membresDisponibles" :key="u.id" :value="u.id">
                                    {{ u.name }} ({{ u.email }})
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.user_id" />
                        </div>
                        <div>
                            <InputLabel for="role_id" value="Rôle dans le Shift" />
                            <select
                                id="role_id"
                                v-model="form.role_id"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                required
                            >
                                <option value="" disabled>Sélectionner</option>
                                <option v-for="r in roles" :key="r.id" :value="r.id">
                                    {{ r.nom }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.role_id" />
                        </div>
                        <div class="flex items-end">
                            <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                        </div>
                    </form>

                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Nom</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Rôle</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Depuis</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <tr v-if="membres.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    Aucun membre affecté pour le moment.
                                </td>
                            </tr>
                            <tr v-for="m in membres" :key="m.affectation_id">
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{{ m.name }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ m.role }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-gray-500 dark:text-gray-400">{{ m.date_debut }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right text-sm">
                                    <DangerButton @click="removeMember(m.affectation_id)">
                                        Retirer
                                    </DangerButton>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
