<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import TextInput from '@/Components/TextInput.vue';
import EtapeToggle from '@/Components/EtapeToggle.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, nextTick, ref } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    shift: Object,
    positions: Array,
    servantsDisponibles: Array,
    postesDisponibles: Array,
});

const { confirmer } = useConfirm();

// Filtre de recherche client sur le tableau des rôles/titulaires, pour
// naviguer facilement dans un roster de 20+ postes sans avoir à tout
// parcourir visuellement.
const recherche = ref('');
const positionsFiltrees = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    if (q === '') return props.positions;

    return props.positions.filter((p) => p.nom.toLowerCase().includes(q)
        || (p.titulaire?.nom_complet.toLowerCase().includes(q) ?? false));
});

const retirerServant = async (positionId, assignmentId) => {
    if (!(await confirmer('Retirer ce serviteur du rôle ?', { danger: true }))) return;
    router.delete(route('shifts.positions.unassign', [props.shift.id, positionId, assignmentId]), {
        preserveScroll: true,
    });
};

const showAddPositionForm = ref(false);
const postesTableRef = ref(null);

// Une seule recherche : si la saisie correspond à un serviteur existant hors
// de ce Shift, on l'affecte directement (autocomplétion) ; sinon on propose
// de le créer à la volée avec ce rôle, sans passer par la page Servants.
const rechercheServant = ref('');
const ouvrirListeServants = ref(false);
const modeNouveauServant = ref(false);

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

const servantsFiltres = computed(() => {
    const q = rechercheServant.value.trim().toLowerCase();
    const source = q === '' ? props.servantsDisponibles : props.servantsDisponibles.filter((s) => s.nom_complet.toLowerCase().includes(q));

    return source.slice(0, 50);
});

const choisirServant = (servant) => {
    form.servant_id = servant.id;
    rechercheServant.value = servant.nom_complet;
    ouvrirListeServants.value = false;
    modeNouveauServant.value = false;
};

const demarrerNouveauServant = () => {
    const [prenom, ...reste] = rechercheServant.value.trim().split(/\s+/);
    form.servant_id = '';
    form.nouveau_servant.prenom = prenom ?? '';
    form.nouveau_servant.nom = reste.join(' ');
    modeNouveauServant.value = true;
    ouvrirListeServants.value = false;
};

const reinitialiserRechercheServant = () => {
    rechercheServant.value = '';
    ouvrirListeServants.value = false;
    modeNouveauServant.value = false;
    form.servant_id = '';
    form.nouveau_servant = { nom: '', prenom: '', genre: '', telephone: '' };
};

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
        ...(modeNouveauServant.value
            ? { nouveau_servant: data.nouveau_servant }
            : { servant_id: data.servant_id }),
    })).post(route('shifts.positions.store', props.shift.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            reinitialiserRechercheServant();
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
                        + Ajouter un serviteur
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

                    <div>
                        <InputLabel for="recherche_servant" value="Serviteur" />
                        <div class="relative mt-1">
                            <input
                                id="recherche_servant"
                                v-model="rechercheServant"
                                type="text"
                                placeholder="Rechercher un serviteur…"
                                class="block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
                                autocomplete="off"
                                @focus="ouvrirListeServants = true"
                                @input="ouvrirListeServants = true; form.servant_id = ''; modeNouveauServant = false"
                                @blur="setTimeout(() => (ouvrirListeServants = false), 150)"
                            />
                            <ul
                                v-if="ouvrirListeServants && rechercheServant.trim() !== ''"
                                class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-neutral-200 dark:bg-neutral-800 dark:ring-neutral-600"
                            >
                                <li
                                    v-for="s in servantsFiltres"
                                    :key="s.id"
                                    class="cursor-pointer px-3 py-2 text-neutral-900 hover:bg-primary-50 dark:text-neutral-100 dark:hover:bg-primary-900/30"
                                    @mousedown.prevent="choisirServant(s)"
                                >
                                    {{ s.nom_complet }}
                                </li>
                                <li
                                    class="cursor-pointer border-t border-neutral-100 px-3 py-2 font-medium text-primary-light hover:bg-primary-50 dark:border-neutral-700 dark:hover:bg-primary-900/30"
                                    @mousedown.prevent="demarrerNouveauServant"
                                >
                                    + Créer « {{ rechercheServant.trim() }} » comme nouveau serviteur
                                </li>
                            </ul>
                        </div>
                        <InputError class="mt-1" :message="form.errors.servant_id" />
                    </div>

                    <div v-if="modeNouveauServant" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
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
                        <PrimaryButton :disabled="form.processing || (!form.servant_id && !modeNouveauServant)">Ajouter</PrimaryButton>
                    </div>
                </form>

                <p v-if="positions.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
                    Aucun rôle pour ce Shift pour le moment.
                </p>

                <template v-else>
                    <div class="mb-3">
                        <TextInput
                            v-model="recherche"
                            type="text"
                            placeholder="Rechercher un rôle ou un titulaire…"
                            class="block w-full sm:w-72"
                        />
                    </div>

                    <div class="overflow-x-auto">
                    <table ref="postesTableRef" class="min-w-full divide-y divide-neutral-100 dark:divide-neutral-700">
                        <thead class="sticky top-0 bg-white dark:bg-neutral-800">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Rôle</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Titulaire</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Appel</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Protection de l'enfance</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Badge</th>
                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-neutral-600 dark:text-neutral-400">Photo</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 dark:divide-neutral-700">
                            <tr v-if="positionsFiltrees.length === 0">
                                <td colspan="6" class="px-3 py-4 text-center text-sm text-neutral-600 dark:text-neutral-400">Aucun résultat pour « {{ recherche }} ».</td>
                            </tr>
                            <tr v-for="position in positionsFiltrees" :key="position.id">
                                <td class="whitespace-nowrap px-3 py-2.5 text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ position.nom }}</td>
                                <template v-if="position.titulaire">
                                    <td class="whitespace-nowrap px-3 py-2.5 text-sm text-neutral-900 dark:text-neutral-100">{{ position.titulaire.nom_complet }}</td>
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
                                    <td colspan="6" class="px-3 py-2.5 text-sm">
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
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
