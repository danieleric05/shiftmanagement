<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import EtapeToggle from '@/Components/EtapeToggle.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    shift: Object,
    membres: Array,
    membresDisponibles: Array,
    positions: Array,
    servantsDisponibles: Array,
});

const optionsServants = computed(() => props.servantsDisponibles.map((s) => ({ value: s.id, label: s.nom_complet })));
const optionsMembres = computed(() => props.membresDisponibles.map((u) => ({ value: u.id, label: `${u.name} (${u.email})` })));

const { confirmer } = useConfirm();

const showAddForm = ref(false);

const form = useForm({
    user_id: '',
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

const removeMember = async (affectationId) => {
    if (!(await confirmer("Confirmer la fin de cette affectation ?", { danger: true }))) return;
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

const retirerServant = async (positionId, assignmentId) => {
    if (!(await confirmer('Retirer ce servant du poste ?', { danger: true }))) return;
    router.delete(route('shifts.positions.unassign', [props.shift.id, positionId, assignmentId]), {
        preserveScroll: true,
    });
};

</script>

<template>
    <Head :title="`Shift : ${shift.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    {{ shift.nom }}
                </h2>
                <Link :href="route('shifts.edit', shift.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier le Shift
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-4xl space-y-6">
            <Link :href="route('shifts.index')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <dl class="grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Jour</dt>
                        <dd class="text-neutral-900">{{ shift.jour }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Horaire</dt>
                        <dd class="text-neutral-900">{{ shift.heure_debut }} - {{ shift.heure_fin }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-lg font-medium text-neutral-900">Postes du Shift</h3>

                <p v-if="positions.length === 0" class="text-sm text-neutral-600">
                    Aucun poste pour ce Shift pour le moment.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Poste</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Titulaire</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Coordonnées</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Appel</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Protection de l'enfance</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Badge</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Photo</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600">Formation</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-for="position in positions" :key="position.id">
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900">{{ position.nom }}</td>
                                <template v-if="position.titulaire">
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-900">{{ position.titulaire.nom_complet }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600">{{ position.titulaire.coordonnees ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-600">{{ position.titulaire.titre_leadership ?? '—' }}</td>
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
                                    <td class="px-3 py-2.5 text-sm">
                                        <EtapeToggle
                                            :servant-id="position.titulaire.id"
                                            :workflow-step-id="position.titulaire.etapes.formation.workflow_step_id"
                                            :termine="position.titulaire.etapes.formation.termine"
                                        />
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right text-sm">
                                        <DangerButton @click="retirerServant(position.id, position.assignment_id)">Retirer</DangerButton>
                                    </td>
                                </template>
                                <template v-else>
                                    <td colspan="7" class="px-3 py-2.5 text-sm font-medium text-warning">Poste vacant</td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right text-sm">
                                        <div class="flex items-center justify-end gap-2">
                                            <SearchableSelect
                                                v-model="positionForms[position.id]"
                                                :options="optionsServants"
                                                placeholder="Rechercher un servant…"
                                                class="w-56"
                                            />
                                            <PrimaryButton @click="affecterServant(position.id)">Affecter</PrimaryButton>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900">Membres affectés</h3>
                    <PrimaryButton @click="showAddForm = !showAddForm">
                        + Affecter un membre
                    </PrimaryButton>
                </div>

                <form v-if="showAddForm" @submit.prevent="addMember" class="mb-6 grid grid-cols-1 gap-4 rounded-md bg-neutral-50 p-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="user_id" value="Membre" />
                        <SearchableSelect
                            id="user_id"
                            v-model="form.user_id"
                            :options="optionsMembres"
                            placeholder="Rechercher un membre…"
                            class="mt-1"
                        />
                        <InputError class="mt-2" :message="form.errors.user_id" />
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                    </div>
                </form>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Nom</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Rôle</th>
                                <th class="px-4 py-2 text-left text-xs font-medium uppercase text-neutral-600">Depuis</th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            <tr v-if="membres.length === 0">
                                <td colspan="4" class="px-4 py-6 text-center text-neutral-600">
                                    Aucun membre affecté pour le moment.
                                </td>
                            </tr>
                            <tr v-for="m in membres" :key="m.affectation_id">
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-900">{{ m.name }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ m.role }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-sm text-neutral-600">{{ m.date_debut }}</td>
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
