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
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import { ArrowLeftRight, Repeat, UserRound } from '@lucide/vue';

const props = defineProps({
    demandes: Object,
    shifts: Array,
    servants: Array,
    filtreType: String,
    filtreRecherche: String,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const typeIcon = {
    releve: Repeat,
    permutation: ArrowLeftRight,
};

const typeLabel = {
    releve: 'Relève',
    permutation: 'Permutation',
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

const updateForms = reactive(
    Object.fromEntries(
        props.demandes.data
            .filter((d) => d.statut === 'en_attente')
            .map((d) => [
                d.id,
                useForm({
                    discussion_servant: d.discussion_servant ?? '',
                    approuve_deux_shifts: d.approuve_deux_shifts ?? false,
                    notes: d.notes ?? '',
                }),
            ]),
    ),
);

const resolveForms = reactive(
    Object.fromEntries(
        props.demandes.data
            .filter((d) => d.statut === 'en_attente')
            .map((d) => [d.id, useForm({ resultat: '', resultat_date: '' })]),
    ),
);

const mettreAJour = (id) => {
    updateForms[id].patch(route('shift-transfers.update', id), { preserveScroll: true });
};

const resoudre = (id) => {
    resolveForms[id].patch(route('shift-transfers.resolve', id), { preserveScroll: true });
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
                <PrimaryButton @click="showCreateForm = !showCreateForm">+ Nouvelle demande</PrimaryButton>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Relèves en attente" :value="compteurs.releves" :icon="Repeat" tone="warning" />
                <StatCard label="Permutations en attente" :value="compteurs.permutations" :icon="ArrowLeftRight" tone="warning" />
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
            </div>

            <form v-if="showCreateForm" @submit.prevent="creerDemande" class="grid grid-cols-1 gap-4 rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 sm:grid-cols-3">
                <div>
                    <InputLabel for="type" value="Type" />
                    <select id="type" v-model="form.type" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="releve">Relève</option>
                        <option value="permutation">Permutation</option>
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
                    <select id="servant_id" v-model="form.servant_id" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light" required>
                        <option value="" disabled>Sélectionner</option>
                        <option v-for="s in servants" :key="s.id" :value="s.id">{{ s.prenom }} {{ s.nom }}</option>
                    </select>
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
                    Aucune {{ filtreType === 'releve' ? 'relève' : 'permutation' }} enregistrée pour l'instant.
                </template>
                <template v-else>
                    Aucune relève ni permutation enregistrée pour l'instant. Utilisez « + Nouvelle demande » pour en créer une.
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
                            Résultat : {{ d.resultat }} ({{ d.resultat_date }}) — par {{ d.decideur }}
                        </div>
                    </div>
                    <StatusBadge :statut="d.statut" />
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
                    <div class="flex items-end justify-end">
                        <SecondaryButton :disabled="updateForms[d.id].processing" @click="mettreAJour(d.id)">
                            Enregistrer
                        </SecondaryButton>
                    </div>

                    <template v-if="estAdministrateur">
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
                        <div class="flex items-end justify-end gap-2">
                            <DangerButton @click="supprimer(d.id)">Supprimer</DangerButton>
                            <SecondaryButton :disabled="resolveForms[d.id].processing" @click="resoudre(d.id)">
                                Enregistrer le résultat
                            </SecondaryButton>
                        </div>
                    </template>
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
