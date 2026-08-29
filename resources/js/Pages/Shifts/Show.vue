<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
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

const showAddPositionForm = ref(false);
const postesTableRef = ref(null);

const positionForm = useForm({
    shift_template_position_id: '',
});

// Un nouveau poste est vacant, donc toujours ajouté en fin de tableau (cf.
// tri occupés/vacants côté serveur) : sans ça, rien ne signale qu'il a bien
// été créé tant qu'on n'a pas fait défiler la page jusqu'en bas.
const scrollerVersDernierPoste = () => {
    const lignes = postesTableRef.value?.querySelectorAll('tbody tr');
    const derniere = lignes?.[lignes.length - 1];
    derniere?.scrollIntoView({ behavior: 'smooth', block: 'center' });
};

const ajouterPoste = () => {
    positionForm.post(route('shifts.positions.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            positionForm.reset();
            showAddPositionForm.value = false;
            nextTick(scrollerVersDernierPoste);
        },
    });
};

const supprimerPoste = async (positionId) => {
    if (!(await confirmer('Supprimer ce poste ?', { danger: true }))) return;
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
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-medium text-neutral-900">Postes du Shift</h3>
                    <PrimaryButton v-if="postesDisponibles.length > 0" @click="showAddPositionForm = !showAddPositionForm">
                        + Ajouter un poste
                    </PrimaryButton>
                </div>

                <form v-if="showAddPositionForm" @submit.prevent="ajouterPoste" class="mb-6 flex items-end gap-3">
                    <div class="flex-1">
                        <InputLabel for="shift_template_position_id" value="Poste" />
                        <select
                            id="shift_template_position_id"
                            v-model="positionForm.shift_template_position_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="p in postesDisponibles" :key="p.id" :value="p.id">{{ p.nom }}</option>
                        </select>
                        <InputError class="mt-1" :message="positionForm.errors.shift_template_position_id" />
                    </div>
                    <PrimaryButton :disabled="positionForm.processing">Ajouter</PrimaryButton>
                </form>

                <p v-if="positions.length === 0" class="text-sm text-neutral-600">
                    Aucun poste pour ce Shift pour le moment.
                </p>

                <div v-else class="overflow-x-auto">
                    <table ref="postesTableRef" class="min-w-full divide-y divide-neutral-100">
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
                                    <td colspan="8" class="px-3 py-2.5 text-sm">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-medium text-warning">Poste vacant</span>
                                            <SearchableSelect
                                                v-model="positionForms[position.id]"
                                                :options="optionsServants"
                                                placeholder="Rechercher un servant…"
                                                class="w-56"
                                            />
                                            <PrimaryButton @click="affecterServant(position.id)">Affecter</PrimaryButton>
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
