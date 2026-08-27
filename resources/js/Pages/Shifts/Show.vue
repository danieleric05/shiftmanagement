<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Badge from '@/Components/Badge.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    shift: Object,
    membres: Array,
    membresDisponibles: Array,
    positions: Array,
    servantsDisponibles: Array,
});

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
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Jour</dt>
                        <dd class="text-neutral-900">{{ shift.jour }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Horaire</dt>
                        <dd class="text-neutral-900">{{ shift.heure_debut }} - {{ shift.heure_fin }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Statut</dt>
                        <dd class="text-neutral-900"><StatusBadge :statut="shift.statut" /></dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase text-neutral-600">Membres actifs</dt>
                        <dd class="text-neutral-900">{{ membres.length }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-lg font-medium text-neutral-900">Postes du Shift</h3>

                <p v-if="positions.length === 0" class="text-sm text-neutral-600">
                    Aucun poste pour ce Shift pour le moment.
                </p>

                <ul v-else class="space-y-3">
                    <li
                        v-for="position in positions"
                        :key="position.id"
                        class="rounded-lg border border-neutral-100 p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-neutral-900">{{ position.nom }}</p>
                                <p v-if="position.titulaire" class="text-sm text-neutral-600">
                                    {{ position.titulaire.nom_complet }}
                                    <span v-if="position.titulaire.coordonnees">· {{ position.titulaire.coordonnees }}</span>
                                    <span v-if="position.titulaire.titre_leadership">· {{ position.titulaire.titre_leadership }}</span>
                                </p>
                                <p v-else class="text-sm font-medium text-warning">Poste vacant</p>
                            </div>

                            <DangerButton v-if="position.titulaire" @click="retirerServant(position.id, position.assignment_id)">
                                Retirer
                            </DangerButton>
                            <div v-else class="flex items-center gap-2">
                                <select
                                    v-model="positionForms[position.id]"
                                    class="rounded-md border-neutral-300 text-sm shadow-sm"
                                >
                                    <option value="">Sélectionner un servant</option>
                                    <option v-for="s in servantsDisponibles" :key="s.id" :value="s.id">
                                        {{ s.nom_complet }}
                                    </option>
                                </select>
                                <PrimaryButton @click="affecterServant(position.id)">Affecter</PrimaryButton>
                            </div>
                        </div>

                        <div v-if="position.titulaire" class="mt-3 flex flex-wrap gap-1.5">
                            <Badge :variant="position.titulaire.etapes.protection_jeunesse ? 'success' : 'neutral'">
                                Protection jeunesse : {{ position.titulaire.etapes.protection_jeunesse ? 'Oui' : 'Non' }}
                            </Badge>
                            <Badge :variant="position.titulaire.etapes.badge ? 'success' : 'neutral'">
                                Badge : {{ position.titulaire.etapes.badge ? 'Oui' : 'Non' }}
                            </Badge>
                            <Badge :variant="position.titulaire.etapes.photo ? 'success' : 'neutral'">
                                Photo : {{ position.titulaire.etapes.photo ? 'Oui' : 'Non' }}
                            </Badge>
                            <Badge :variant="position.titulaire.etapes.orientation ? 'success' : 'neutral'">
                                Orientation : {{ position.titulaire.etapes.orientation ? 'Oui' : 'Non' }}
                            </Badge>
                            <Badge :variant="position.titulaire.etapes.formation ? 'success' : 'neutral'">
                                Formation : {{ position.titulaire.etapes.formation ? 'Oui' : 'Non' }}
                            </Badge>
                        </div>
                    </li>
                </ul>
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
                        <select
                            id="user_id"
                            v-model="form.user_id"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm"
                            required
                        >
                            <option value="" disabled>Sélectionner</option>
                            <option v-for="u in membresDisponibles" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.email }})
                            </option>
                        </select>
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
