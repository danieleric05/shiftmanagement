<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import { ChevronUp, ChevronDown, GripVertical } from '@lucide/vue';

const props = defineProps({
    template: Object,
    positions: Array,
});

const { confirmer } = useConfirm();

// Copie locale pour un retour visuel immédiat pendant le glisser-déposer ;
// resynchronisée à chaque réponse du serveur (props.positions).
const positionsAffichees = ref([...props.positions]);
watch(() => props.positions, (val) => (positionsAffichees.value = [...val]));

// Un couple homme/femme (même "bloc", calculé par le serveur) se déplace
// d'un seul tenant : glisser l'un des deux postes emmène l'autre.
const blocGlisse = ref(null);
const blocSurvole = ref(null);

const dernierBloc = computed(() =>
    positionsAffichees.value.reduce((max, p) => Math.max(max, p.bloc), -1),
);

const onDragStart = (position) => {
    blocGlisse.value = position.bloc;
};

const onDrop = (position) => {
    blocSurvole.value = null;
    const source = blocGlisse.value;
    blocGlisse.value = null;
    if (source === null || source === position.bloc) {
        return;
    }

    const groupes = [];
    for (const p of positionsAffichees.value) {
        const dernier = groupes[groupes.length - 1];
        if (dernier && dernier[0].bloc === p.bloc) {
            dernier.push(p);
        } else {
            groupes.push([p]);
        }
    }

    const de = groupes.findIndex((g) => g[0].bloc === source);
    const vers = groupes.findIndex((g) => g[0].bloc === position.bloc);
    const [deplace] = groupes.splice(de, 1);
    groupes.splice(vers, 0, deplace);

    const items = groupes.flatMap((g, i) => g.map((p) => ({ ...p, bloc: i, numero: i + 1 })));
    positionsAffichees.value = items;

    router.patch(route('shift-templates.positions.reorder', props.template.id), {
        positions: items.map((p) => p.id),
    }, { preserveScroll: true });
};

const form = useForm({
    nom: '',
});

const ajouterPoste = () => {
    form.post(route('shift-templates.positions.store', props.template.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const enEdition = ref(null);
const editForms = reactive({});

const editerPoste = (position) => {
    editForms[position.id] = useForm({ nom: position.nom });
    enEdition.value = position.id;
};

const enregistrerPoste = (positionId) => {
    editForms[positionId].put(route('shift-templates.positions.update', [props.template.id, positionId]), {
        preserveScroll: true,
        onSuccess: () => (enEdition.value = null),
    });
};

const supprimerPoste = async (positionId) => {
    if (!(await confirmer('Supprimer ce poste du modèle ?', { danger: true }))) return;
    router.delete(route('shift-templates.positions.destroy', [props.template.id, positionId]), {
        preserveScroll: true,
    });
};

const deplacerPoste = (positionId, direction) => {
    router.patch(route('shift-templates.positions.move', [props.template.id, positionId]), { direction }, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="template.nom" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de Shift', href: route('shift-templates.index') }, { label: template.nom }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    {{ template.nom }}
                </h2>
                <Link :href="route('shift-templates.edit', template.id)" class="text-sm font-medium text-primary-light hover:text-primary">
                    Modifier
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <Link :href="route('shift-templates.index')" class="text-sm text-neutral-600 dark:text-neutral-400 hover:text-neutral-900 dark:hover:text-neutral-100">← Retour</Link>

            <div v-if="template.description" class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <p class="text-neutral-600 dark:text-neutral-400">{{ template.description }}</p>
            </div>

            <div class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                <h3 class="mb-4 text-lg font-medium text-neutral-900 dark:text-neutral-100">Postes du modèle</h3>

                <form @submit.prevent="ajouterPoste" class="mb-6 flex gap-3">
                    <TextInput
                        v-model="form.nom"
                        type="text"
                        class="block w-full"
                        placeholder="Ex: Coordonnateur Adjoint"
                        required
                    />
                    <PrimaryButton :disabled="form.processing">Ajouter</PrimaryButton>
                </form>

                <p v-if="positionsAffichees.length > 1" class="mb-2 text-xs text-neutral-500 dark:text-neutral-400">
                    Glissez une ligne (poignée à gauche) pour la repositionner. Un poste féminin reste toujours sous son poste masculin : le couple se déplace ensemble.
                </p>

                <ul class="divide-y divide-neutral-100 dark:divide-neutral-700">
                    <li v-if="positionsAffichees.length === 0" class="py-6 text-center text-neutral-600 dark:text-neutral-400">
                        Aucun poste défini pour ce modèle.
                    </li>
                    <li
                        v-for="position in positionsAffichees"
                        :key="position.id"
                        class="flex items-center justify-between gap-3 py-3"
                        :class="{ 'opacity-40': blocGlisse === position.bloc, 'bg-primary-50/60 dark:bg-primary-900/20': blocSurvole === position.bloc && blocGlisse !== position.bloc }"
                        :draggable="enEdition !== position.id"
                        @dragstart="onDragStart(position)"
                        @dragover.prevent="blocSurvole = position.bloc"
                        @dragleave="blocSurvole = null"
                        @drop="onDrop(position)"
                        @dragend="blocGlisse = null; blocSurvole = null"
                    >
                        <template v-if="enEdition === position.id">
                            <form @submit.prevent="enregistrerPoste(position.id)" class="flex flex-1 items-start gap-2">
                                <div class="flex-1">
                                    <TextInput v-model="editForms[position.id].nom" type="text" class="block w-full" required />
                                    <InputError class="mt-1" :message="editForms[position.id].errors.nom" />
                                </div>
                                <PrimaryButton :disabled="editForms[position.id].processing">Enregistrer</PrimaryButton>
                                <button type="button" class="text-sm text-neutral-600 dark:text-neutral-400" @click="enEdition = null">Annuler</button>
                            </form>
                        </template>
                        <template v-else>
                            <span class="flex items-center gap-2 text-neutral-900 dark:text-neutral-100">
                                <GripVertical class="h-4 w-4 shrink-0 cursor-grab text-neutral-400 active:cursor-grabbing" />
                                {{ position.numero }}. {{ position.nom }}
                            </span>
                            <div class="flex shrink-0 items-center gap-1">
                                <button
                                    type="button"
                                    :disabled="position.bloc === 0"
                                    class="rounded p-1 text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-600 hover:text-neutral-900 dark:hover:text-neutral-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Monter"
                                    @click="deplacerPoste(position.id, 'haut')"
                                >
                                    <ChevronUp class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    :disabled="position.bloc === dernierBloc"
                                    class="rounded p-1 text-neutral-500 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-600 hover:text-neutral-900 dark:hover:text-neutral-100 disabled:opacity-30 disabled:hover:bg-transparent"
                                    title="Descendre"
                                    @click="deplacerPoste(position.id, 'bas')"
                                >
                                    <ChevronDown class="h-4 w-4" />
                                </button>
                                <button type="button" class="ml-2 text-sm font-medium text-primary-light hover:text-primary" @click="editerPoste(position)">
                                    Modifier
                                </button>
                                <DangerButton @click="supprimerPoste(position.id)">Retirer</DangerButton>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
