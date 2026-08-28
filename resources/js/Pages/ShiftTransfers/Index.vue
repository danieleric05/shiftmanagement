<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import StatCard from '@/Components/StatCard.vue';
import Badge from '@/Components/Badge.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SearchableSelect from '@/Components/SearchableSelect.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { ArrowLeftRight, Phone, Repeat, UserRound } from '@lucide/vue';

const props = defineProps({
    demandes: Object,
    shifts: Array,
    servants: Array,
    filtreType: String,
    filtreRecherche: String,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const optionsServants = computed(() => props.servants.map((s) => ({ value: s.id, label: `${s.prenom} ${s.nom}` })));

const typeIcon = {
    releve: Repeat,
    permutation: ArrowLeftRight,
    appel: Phone,
};

const typeLabel = {
    releve: 'Relève',
    permutation: 'Permutation',
    appel: 'Appel',
};

const showCreateForm = ref(false);

const form = useForm({
    shift_id: '',
    type: 'releve',
    servant_id: '',
    shift_destination_id: '',
    motif: '',
    date_demande: '',
    discussion_servant: '',
    approuve_deux_shifts: false,
    notes: '',
});

const creerDemande = () => {
    form.post(route('shift-transfers.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateForm.value = false;
        },
    });
};

const recherche = ref(props.filtreRecherche ?? '');

const filtrer = (type) => {
    router.get(route('shift-transfers.index'), {
        ...(type ? { type } : {}),
        ...(recherche.value ? { recherche: recherche.value } : {}),
    }, { preserveState: true, replace: true });
};

let rechercheTimeout = null;
const rechercherAvecDelai = () => {
    clearTimeout(rechercheTimeout);
    rechercheTimeout = setTimeout(() => filtrer(props.filtreType), 300);
};

// `updateForms`/`resolveForms` sont indexés par demande et créés à la volée :
// les visites Inertia déclenchées par ces actions (valider/mettre à jour/
// résoudre) préservent l'état local du composant par défaut, donc de
// nouvelles demandes peuvent apparaître dans `demandes.data` (filtre,
// pagination, changement de statut) sans que le composant soit remonté.
// Construire ces formulaires une seule fois au montage laisserait
// `updateForms[d.id]`/`resolveForms[d.id]` undefined pour ces nouvelles
// demandes et ferait planter le rendu (page blanche).
const updateForms = reactive({});
const resolveForms = reactive({});

const assurerFormulaires = (demandesData) => {
    demandesData
        .filter((d) => d.statut === 'en_attente')
        .forEach((d) => {
            if (!updateForms[d.id]) {
                updateForms[d.id] = useForm({
                    discussion_servant: d.discussion_servant ?? '',
                    approuve_deux_shifts: d.approuve_deux_shifts ?? false,
                    entretien_date: d.entretien_date ?? '',
                    entretien_heure: d.entretien_heure ?? '',
                    notes: d.notes ?? '',
                });
            }
            if (!resolveForms[d.id]) {
                resolveForms[d.id] = useForm({ resultat: '', resultat_date: '', favorable: null, shift_position_destination_id: '' });
            }
        });
};

assurerFormulaires(props.demandes.data);
watch(() => props.demandes.data, (demandesData) => assurerFormulaires(demandesData));

const mettreAJour = (id) => {
    updateForms[id].patch(route('shift-transfers.update', id), { preserveScroll: true });
};

const resoudre = (demande) => {
    resolveForms[demande.id]
        .transform((data) => (['permutation', 'appel'].includes(demande.type) ? data : { resultat: data.resultat, resultat_date: data.resultat_date }))
        .patch(route('shift-transfers.resolve', demande.id), { preserveScroll: true });
};

const validerOrigine = (id, accepte) => {
    router.patch(route('shift-transfers.valider-origine', id), { accepte }, { preserveScroll: true });
};

const validerDestination = (id, accepte) => {
    router.patch(route('shift-transfers.valider-destination', id), { accepte }, { preserveScroll: true });
};

const supprimer = (id) => {
    router.delete(route('shift-transfers.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Transferts" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900">
                    <Repeat class="h-5 w-5 text-primary" />
                    Relèves &amp; permutations
                </h2>
                <div class="flex items-center gap-4">
                    <Link :href="route('shift-transfers.releves')" class="text-sm font-medium text-primary-light hover:text-primary">
                        Servants relevés →
                    </Link>
                    <PrimaryButton @click="showCreateForm = !showCreateForm">+ Nouvelle demande</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-3 gap-4">
                <StatCard label="Relèves en attente" :value="compteurs.releves" :icon="Repeat" tone="warning" />
                <StatCard label="Permutations en attente" :value="compteurs.permutations" :icon="ArrowLeftRight" tone="warning" />
                <StatCard label="Appels en attente" :value="compteurs.appels" :icon="Phone" tone="warning" />
            </div>

            <SearchInput
                :model-value="recherche"
                placeholder="Rechercher un servant…"
                @update:model-value="(v) => { recherche = v; rechercherAvecDelai(); }"
            />

            <div class="flex gap-2">
                <button
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="!filtreType ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200'"
                    @click="filtrer('')"
                >
                    Toutes
                </button>
                <button
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="filtreType === 'releve' ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200'"
                    @click="filtrer('releve')"
                >
                    Relèves
                </button>
                <button
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="filtreType === 'permutation' ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200'"
                    @click="filtrer('permutation')"
                >
                    Permutations
                </button>
                <button
                    class="rounded-full px-3 py-1 text-sm font-medium"
                    :class="filtreType === 'appel' ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200'"
                    @click="filtrer('appel')"
                >
                    Appels
                </button>
            </div>

            <form v-if="showCreateForm" @submit.prevent="creerDemande" class="grid grid-cols-1 gap-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 sm:grid-cols-3">
                <div>
                    <InputLabel for="type" value="Type" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="releve">Relève</option>
                        <option value="permutation">Permutation</option>
                        <option value="appel">Appel</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.type" />
                </div>
                <div>
                    <InputLabel for="shift_id" value="Shift" />
                    <select id="shift_id" v-model="form.shift_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_id" />
                </div>
                <div v-if="form.type === 'permutation'">
                    <InputLabel for="shift_destination_id" value="Shift de destination" />
                    <select id="shift_destination_id" v-model="form.shift_destination_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light">
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.nom }}</option>
                    </select>
                    <InputError class="mt-2" :message="form.errors.shift_destination_id" />
                </div>
                <div>
                    <InputLabel for="servant_id" value="Servant" />
                    <SearchableSelect
                        id="servant_id"
                        v-model="form.servant_id"
                        :options="optionsServants"
                        placeholder="Rechercher un servant…"
                        class="mt-1"
                    />
                    <InputError class="mt-2" :message="form.errors.servant_id" />
                </div>
                <div>
                    <InputLabel for="date_demande" value="Date de la demande" />
                    <input id="date_demande" v-model="form.date_demande" type="date" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required />
                    <InputError class="mt-2" :message="form.errors.date_demande" />
                </div>
                <div class="flex items-center gap-2 pt-6">
                    <input id="approuve_deux_shifts" v-model="form.approuve_deux_shifts" type="checkbox" class="rounded border-neutral-300" />
                    <InputLabel for="approuve_deux_shifts" value="Approuvé par les deux Shifts" />
                </div>
                <div class="sm:col-span-3">
                    <InputLabel for="motif" value="Motif" />
                    <textarea id="motif" v-model="form.motif" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required></textarea>
                    <InputError class="mt-2" :message="form.errors.motif" />
                </div>
                <div class="sm:col-span-3">
                    <InputLabel for="discussion_servant" value="Discussion avec le servant" />
                    <textarea id="discussion_servant" v-model="form.discussion_servant" rows="2" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"></textarea>
                    <InputError class="mt-2" :message="form.errors.discussion_servant" />
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <PrimaryButton :disabled="form.processing">Soumettre</PrimaryButton>
                </div>
            </form>

            <div v-if="demandes.data.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                <template v-if="filtreRecherche">
                    Aucune demande ne correspond à « {{ filtreRecherche }} ».
                </template>
                <template v-else-if="filtreType">
                    Aucune demande de type « {{ typeLabel[filtreType] }} » enregistrée pour l'instant.
                </template>
                <template v-else>
                    Aucune relève, permutation ni appel enregistré pour l'instant. Utilisez « + Nouvelle demande » pour en créer une.
                </template>
            </div>

            <div
                v-for="d in demandes.data"
                :key="d.id"
                class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 transition hover:shadow-md"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="neutral">
                                <component :is="typeIcon[d.type]" class="h-3.5 w-3.5" />
                                {{ typeLabel[d.type] }}
                            </Badge>
                            <span class="font-medium text-neutral-900">{{ d.servant }}</span>
                        </div>
                        <div class="mt-2 flex items-center gap-1.5 text-sm text-neutral-600">
                            <span class="rounded-md bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700">{{ d.shift }}</span>
                            <template v-if="d.shift_destination">
                                <ArrowLeftRight class="h-3.5 w-3.5 text-neutral-400" />
                                <span class="rounded-md bg-neutral-100 px-2 py-0.5 font-medium text-neutral-700">{{ d.shift_destination }}</span>
                            </template>
                        </div>
                        <div class="mt-1.5 flex items-center gap-1 text-xs text-neutral-500">
                            <UserRound class="h-3.5 w-3.5" />
                            Demandé le {{ d.date_demande }} par {{ d.demandeur }}
                            <span v-if="d.coordonnees">· {{ d.coordonnees }}</span>
                        </div>
                        <p class="mt-2 text-sm text-neutral-600">{{ d.motif }}</p>
                        <p v-if="d.discussion_servant" class="mt-1 text-sm text-neutral-600">
                            Discussion : {{ d.discussion_servant }}
                        </p>
                        <p v-if="d.approuve_deux_shifts" class="mt-1 text-xs text-success-700">Approuvé par les deux Shifts</p>
                        <div v-if="d.statut === 'traitee'" class="mt-2 text-sm text-neutral-600">
                            <Badge v-if="['permutation', 'appel'].includes(d.type) && d.favorable !== null" :variant="d.favorable ? 'success' : 'danger'" class="mr-1.5">
                                {{ d.favorable ? 'Favorable' : 'Défavorable' }}
                            </Badge>
                            Résultat : {{ d.resultat }} ({{ d.resultat_date }}) — par {{ d.decideur }}
                        </div>
                    </div>
                    <StatusBadge :statut="d.statut" />
                </div>

                <!-- Double validation des coordonnateurs d'équipe (permutation uniquement) -->
                <div v-if="d.type === 'permutation' && d.statut === 'en_attente'" class="mt-4 flex flex-wrap items-center gap-4 border-t border-neutral-100 pt-4 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-neutral-600">Coordonnateur d'origine :</span>
                        <Badge v-if="d.validation_chef_origine === true" variant="success">Validé{{ d.validation_chef_origine_par ? ` par ${d.validation_chef_origine_par}` : '' }}</Badge>
                        <Badge v-else-if="d.validation_chef_origine === false" variant="danger">Refusé</Badge>
                        <template v-else>
                            <Badge variant="warning">En attente</Badge>
                            <template v-if="d.peut_valider_origine">
                                <button type="button" class="text-xs font-medium text-success-700 hover:underline" @click="validerOrigine(d.id, true)">Valider</button>
                                <button type="button" class="text-xs font-medium text-danger hover:underline" @click="validerOrigine(d.id, false)">Refuser</button>
                            </template>
                        </template>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-neutral-600">Coordonnateur de destination :</span>
                        <Badge v-if="d.validation_chef_destination === true" variant="success">Validé{{ d.validation_chef_destination_par ? ` par ${d.validation_chef_destination_par}` : '' }}</Badge>
                        <Badge v-else-if="d.validation_chef_destination === false" variant="danger">Refusé</Badge>
                        <template v-else>
                            <Badge variant="warning">En attente</Badge>
                            <template v-if="d.peut_valider_destination">
                                <button type="button" class="text-xs font-medium text-success-700 hover:underline" @click="validerDestination(d.id, true)">Valider</button>
                                <button type="button" class="text-xs font-medium text-danger hover:underline" @click="validerDestination(d.id, false)">Refuser</button>
                            </template>
                        </template>
                    </div>
                </div>

                <div v-if="d.statut === 'en_attente'" class="mt-4 grid grid-cols-1 gap-4 border-t border-neutral-100 pt-4 sm:grid-cols-2">
                    <div>
                        <InputLabel :for="`discussion-${d.id}`" value="Discussion avec le servant" />
                        <textarea
                            :id="`discussion-${d.id}`"
                            v-model="updateForms[d.id].discussion_servant"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        ></textarea>
                    </div>
                    <div>
                        <InputLabel :for="`notes-${d.id}`" value="Notes" />
                        <textarea
                            :id="`notes-${d.id}`"
                            v-model="updateForms[d.id].notes"
                            rows="2"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        ></textarea>
                    </div>
                    <div class="flex items-center gap-2">
                        <input :id="`approuve-${d.id}`" v-model="updateForms[d.id].approuve_deux_shifts" type="checkbox" class="rounded border-neutral-300" />
                        <InputLabel :for="`approuve-${d.id}`" value="Approuvé par les deux Shifts" />
                    </div>
                    <template v-if="d.type === 'permutation' && estAdministrateur && d.validation_chef_origine && d.validation_chef_destination">
                        <div>
                            <InputLabel :for="`entretien-date-${d.id}`" value="Date de l'entretien" />
                            <input
                                :id="`entretien-date-${d.id}`"
                                v-model="updateForms[d.id].entretien_date"
                                type="date"
                                class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                            />
                        </div>
                        <div>
                            <InputLabel :for="`entretien-heure-${d.id}`" value="Heure de l'entretien" />
                            <input
                                :id="`entretien-heure-${d.id}`"
                                v-model="updateForms[d.id].entretien_heure"
                                type="time"
                                class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                            />
                        </div>
                    </template>
                    <div class="flex items-end justify-end">
                        <SecondaryButton :disabled="updateForms[d.id].processing" @click="mettreAJour(d.id)">
                            Enregistrer
                        </SecondaryButton>
                    </div>

                    <template v-if="estAdministrateur && (d.type !== 'permutation' || (d.validation_chef_origine && d.validation_chef_destination))">
                        <div class="sm:col-span-2 border-t border-neutral-100 pt-4">
                            <InputLabel :for="`resultat-${d.id}`" value="Résultat" />
                            <textarea
                                :id="`resultat-${d.id}`"
                                v-model="resolveForms[d.id].resultat"
                                rows="2"
                                class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                            ></textarea>
                            <InputError class="mt-1" :message="resolveForms[d.id].errors.resultat" />
                        </div>
                        <div>
                            <InputLabel :for="`resultat-date-${d.id}`" value="Date du résultat" />
                            <input
                                :id="`resultat-date-${d.id}`"
                                v-model="resolveForms[d.id].resultat_date"
                                type="date"
                                class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                            />
                            <InputError class="mt-1" :message="resolveForms[d.id].errors.resultat_date" />
                        </div>

                        <template v-if="['permutation', 'appel'].includes(d.type)">
                            <div>
                                <InputLabel value="Décision" />
                                <div class="mt-1 flex items-center gap-4">
                                    <label class="flex items-center gap-1.5 text-sm text-neutral-700">
                                        <input type="radio" :name="`favorable-${d.id}`" :value="true" v-model="resolveForms[d.id].favorable" />
                                        Favorable
                                    </label>
                                    <label class="flex items-center gap-1.5 text-sm text-neutral-700">
                                        <input type="radio" :name="`favorable-${d.id}`" :value="false" v-model="resolveForms[d.id].favorable" />
                                        Défavorable
                                    </label>
                                </div>
                                <InputError class="mt-1" :message="resolveForms[d.id].errors.favorable" />
                            </div>
                            <div v-if="resolveForms[d.id].favorable === true">
                                <InputLabel :for="`poste-destination-${d.id}`" :value="d.type === 'permutation' ? 'Poste sur le shift de destination' : 'Poste sur son shift'" />
                                <select
                                    :id="`poste-destination-${d.id}`"
                                    v-model="resolveForms[d.id].shift_position_destination_id"
                                    class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                                >
                                    <option value="" disabled>Sélectionner</option>
                                    <option v-for="poste in d.postes_destination_vacants" :key="poste.id" :value="poste.id">{{ poste.nom }}</option>
                                </select>
                                <p v-if="d.postes_destination_vacants.length === 0" class="mt-1 text-xs text-warning">Aucun poste vacant sur ce shift.</p>
                                <InputError class="mt-1" :message="resolveForms[d.id].errors.shift_position_destination_id" />
                            </div>
                        </template>

                        <div class="flex items-end justify-end gap-2" :class="['permutation', 'appel'].includes(d.type) ? 'sm:col-span-2' : ''">
                            <DangerButton @click="supprimer(d.id)">Supprimer</DangerButton>
                            <SecondaryButton :disabled="resolveForms[d.id].processing" @click="resoudre(d)">
                                Enregistrer le résultat
                            </SecondaryButton>
                        </div>
                    </template>
                    <div v-else-if="estAdministrateur && d.type === 'permutation'" class="sm:col-span-2 text-sm italic text-neutral-500">
                        En attente de la validation des deux coordonnateurs d'équipe avant de pouvoir statuer.
                    </div>
                </div>
            </div>

            <div v-if="demandes.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in demandes.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
