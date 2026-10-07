<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DataTable from '@/Components/DataTable.vue';
import EtapesAffectationModal from '@/Components/EtapesAffectationModal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Pencil } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    shift: Object,
    membres: Array,
    positions: Array,
    estMonShift: Boolean,
});

// Rôle « Autres » : jamais d'action (comme EtapeToggle auparavant).
const peutModifier = computed(() => props.estMonShift && !usePage().props.auth?.lectureSeule);

// ---- Équipe de coordination (tri client) ----
const colonnesMembres = [
    { cle: 'name', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 180 },
    { cle: 'role', libelle: 'Rôle', triable: true, priorite: 1, largeurMin: 150 },
    { cle: 'date_debut', libelle: 'Depuis', triable: true, priorite: 2, largeurMin: 110 },
];

// ---- Rôles et titulaires (tri client ; ordre par défaut : occupés puis vacants) ----
const ETAPES = [
    { cle: 'protection_jeunesse', libelle: "Protection de l'enfance" },
    { cle: 'badge', libelle: 'Badge' },
    { cle: 'photo', libelle: 'Photo' },
];
const etapeDe = (p, cle) => p.titulaire?.etapes?.[cle] ?? null;

const colonnesPostes = [
    { cle: 'nom', libelle: 'Rôle', triable: true, principale: true, priorite: 1, largeurMin: 160, tronquer: false },
    { cle: 'titulaire', libelle: 'Titulaire', triable: true, priorite: 1, largeurMin: 170, valeur: (p) => p.titulaire?.nom_complet ?? null },
    { cle: 'appel', libelle: 'Appel', triable: true, priorite: 3, largeurMin: 120, valeur: (p) => p.titulaire?.titre_leadership ?? null },
    ...ETAPES.map((e) => ({
        cle: e.cle,
        libelle: e.libelle,
        triable: true,
        priorite: 2,
        largeurMin: e.cle === 'protection_jeunesse' ? 130 : 95,
        tronquer: false,
        valeurTri: (p) => (p.titulaire ? etapeDe(p, e.cle)?.termine === true : null),
    })),
];

// ---- Modification d'une affectation (fenêtre modale) ----
const idEnEdition = ref(null);
const enEdition = computed(() => props.positions.find((p) => p.id === idEnEdition.value && p.titulaire) ?? null);
</script>

<template>
    <Head :title="shift.nom" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: shift.nom }]">
        <template #header>
            <div class="flex min-w-0 items-center justify-between gap-4">
                <div class="flex min-w-0 items-center gap-2">
                    <h2 class="min-w-0 truncate text-xl font-semibold capitalize leading-tight text-neutral-900 dark:text-neutral-100" :title="`${shift.jour} — ${shift.nom}`">
                        {{ shift.jour }} — {{ shift.nom }}
                    </h2>
                    <Badge v-if="estMonShift" variant="success" class="shrink-0">Mon shift</Badge>
                    <Badge v-else variant="neutral" class="shrink-0">Lecture seule</Badge>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex min-h-[44px] shrink-0 items-center rounded text-sm text-neutral-600 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-400 dark:hover:text-neutral-100"
                >← Retour</Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                {{ shift.heure_debut }} – {{ shift.heure_fin }} ·
                <template v-if="estMonShift">
                    Pour toute modification, utilisez les
                    <Link :href="route('shift-transfers.index')" class="font-medium text-primary-light hover:text-primary">demandes de permutation</Link>.
                </template>
                <template v-else>
                    Consultation seule — ce shift n'est pas géré par vous, contactez son coordinateur ou l'administrateur pour toute modification.
                </template>
            </p>

            <section aria-labelledby="titre-coordination" class="space-y-3">
                <h3 id="titre-coordination" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Équipe de coordination</h3>
                <DataTable
                    :colonnes="colonnesMembres"
                    :lignes="membres"
                    legende="Équipe de coordination du shift"
                    :cle-ligne="(m) => `${m.user_id}-${m.role}`"
                    message-vide="Aucun membre affecté."
                    :compteur="`${membres.length} membre${membres.length > 1 ? 's' : ''}`"
                />
            </section>

            <section aria-labelledby="titre-postes" class="space-y-3">
                <h3 id="titre-postes" class="text-base font-semibold text-neutral-900 dark:text-neutral-100">Rôles &amp; servant(e)s affecté(e)s</h3>
                <DataTable
                    :colonnes="colonnesPostes"
                    :lignes="positions"
                    legende="Rôles et servant(e)s affecté(e)s du shift"
                    message-vide="Aucun rôle défini pour ce shift."
                    :compteur="`${positions.length} rôle${positions.length > 1 ? 's' : ''}`"
                >
                    <template #cellule-titulaire="{ ligne }">
                        <Link
                            v-if="ligne.titulaire"
                            :href="route('servants.mine.show', ligne.titulaire.id)"
                            :title="ligne.titulaire.nom_complet"
                            class="-my-3 inline-block max-w-full truncate rounded align-top font-medium leading-[44px] text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                        >{{ ligne.titulaire.nom_complet }}</Link>
                        <span v-else class="font-medium text-warning-700 dark:text-warning-300">⚠ Personne manquante</span>
                    </template>
                    <template #cellule-protection_jeunesse="{ ligne }">
                        <template v-if="ligne.titulaire">
                            <Badge v-if="etapeDe(ligne, 'protection_jeunesse')?.termine" variant="success">Oui</Badge>
                            <Badge v-else variant="neutral">Non</Badge>
                        </template>
                        <span v-else class="text-neutral-400">—</span>
                    </template>
                    <template #cellule-badge="{ ligne }">
                        <template v-if="ligne.titulaire">
                            <Badge v-if="etapeDe(ligne, 'badge')?.termine" variant="success">Oui</Badge>
                            <Badge v-else variant="neutral">Non</Badge>
                        </template>
                        <span v-else class="text-neutral-400">—</span>
                    </template>
                    <template #cellule-photo="{ ligne }">
                        <template v-if="ligne.titulaire">
                            <Badge v-if="etapeDe(ligne, 'photo')?.termine" variant="success">Oui</Badge>
                            <Badge v-else variant="neutral">Non</Badge>
                        </template>
                        <span v-else class="text-neutral-400">—</span>
                    </template>

                    <template v-if="peutModifier" #actions="{ ligne, mode }">
                        <template v-if="ligne.titulaire">
                            <button
                                v-if="mode === 'tableau'"
                                type="button"
                                class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-primary-light ring-1 ring-neutral-200 transition hover:bg-primary-50 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:ring-neutral-600 dark:hover:bg-neutral-700"
                                :aria-label="`Modifier les étapes de ${ligne.titulaire.nom_complet} (${ligne.nom})`"
                                :title="`Modifier les étapes de ${ligne.titulaire.nom_complet}`"
                                @click="idEnEdition = ligne.id"
                            >
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                            </button>
                            <SecondaryButton v-else class="min-h-[44px]" :aria-label="`Modifier les étapes de ${ligne.titulaire.nom_complet} (${ligne.nom})`" @click="idEnEdition = ligne.id">
                                <Pencil class="h-4 w-4" aria-hidden="true" />
                                Modifier
                            </SecondaryButton>
                        </template>
                        <span v-else class="text-sm text-neutral-400" aria-hidden="true">—</span>
                    </template>
                </DataTable>
            </section>
        </div>

        <EtapesAffectationModal v-if="peutModifier" :position="enEdition" @close="idEnEdition = null" />
    </AuthenticatedLayout>
</template>
