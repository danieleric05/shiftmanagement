<script setup>
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { router } from '@inertiajs/vue3';
import { ref, useId } from 'vue';

/**
 * Fenêtre de modification d'une affectation (fiche d'un Shift, Mon shift) :
 * étapes clés du parcours du titulaire (protection de l'enfance, badge,
 * photo), chacune basculée immédiatement (même requête que EtapeToggle).
 * Remplace l'édition en ligne dans les tableaux.
 *
 * position : { nom, titulaire: { id, nom_complet, titre_leadership, depuis,
 * etapes: { <cle>: { workflow_step_id, termine } } } } ou null (fermée).
 * Slot `actions` : actions supplémentaires en pied (ex. Retirer du rôle).
 */
defineProps({
    position: { type: Object, default: null },
});

const emit = defineEmits(['close']);

const ETAPES = [
    { cle: 'protection_jeunesse', libelle: "Protection de l'enfance" },
    { cle: 'badge', libelle: 'Badge' },
    { cle: 'photo', libelle: 'Photo' },
];

const id = useId();
const enCours = ref(null);

const etapeDe = (p, cle) => p?.titulaire?.etapes?.[cle] ?? null;

const basculer = (p, cle) => {
    const etape = etapeDe(p, cle);
    if (!etape?.workflow_step_id || enCours.value) return;

    enCours.value = cle;
    router.patch(route('servants.workflow.update', [p.titulaire.id, etape.workflow_step_id]), {
        statut: etape.termine ? 'en_attente' : 'termine',
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (enCours.value = null),
    });
};
</script>

<template>
    <Modal :show="position !== null" max-width="lg" :labelledby="`${id}-titre`" @close="emit('close')">
        <div v-if="position" class="p-6">
            <h2 :id="`${id}-titre`" class="break-words text-lg font-semibold text-neutral-900 [overflow-wrap:anywhere] dark:text-neutral-100">
                Affectation : {{ position.nom }}
            </h2>
            <p class="mt-1 break-words text-sm text-neutral-600 [overflow-wrap:anywhere] dark:text-neutral-400">
                {{ position.titulaire.nom_complet }}
                <template v-if="position.titulaire.titre_leadership"> · {{ position.titulaire.titre_leadership }}</template>
                <template v-if="position.titulaire.depuis"> · depuis le {{ position.titulaire.depuis }}</template>
            </p>

            <fieldset class="mt-5">
                <legend class="text-sm font-medium text-neutral-800 dark:text-neutral-200">Étapes du parcours</legend>
                <p class="text-xs text-neutral-600 dark:text-neutral-400">Chaque changement est enregistré immédiatement.</p>
                <ul role="list" class="mt-3 divide-y divide-neutral-100 rounded-lg ring-1 ring-neutral-200 dark:divide-neutral-700 dark:ring-neutral-700">
                    <li v-for="e in ETAPES" :key="e.cle" class="flex min-h-[56px] items-center justify-between gap-3 px-4 py-2">
                        <span :id="`${id}-${e.cle}`" class="min-w-0 text-sm text-neutral-800 dark:text-neutral-200">{{ e.libelle }}</span>
                        <button
                            type="button"
                            role="switch"
                            :aria-checked="etapeDe(position, e.cle)?.termine ? 'true' : 'false'"
                            :aria-labelledby="`${id}-${e.cle}`"
                            :disabled="!etapeDe(position, e.cle)?.workflow_step_id || enCours !== null"
                            :title="etapeDe(position, e.cle)?.workflow_step_id ? undefined : 'Étape absente du parcours de ce servant(e).'"
                            class="inline-flex min-h-[44px] min-w-[5.5rem] shrink-0 items-center justify-center rounded-lg px-3 text-sm font-medium ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-60"
                            :class="etapeDe(position, e.cle)?.termine
                                ? 'bg-success-50 text-success-700 ring-success/30 hover:bg-success-100 dark:bg-success-900/30 dark:text-success-300 dark:ring-success-700/40'
                                : 'bg-white text-neutral-700 ring-neutral-300 hover:bg-neutral-100 dark:bg-neutral-900 dark:text-neutral-200 dark:ring-neutral-600 dark:hover:bg-neutral-700'"
                            @click="basculer(position, e.cle)"
                        >
                            {{ etapeDe(position, e.cle)?.termine ? 'Oui' : 'Non' }}
                        </button>
                    </li>
                </ul>
            </fieldset>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-3">
                    <slot name="actions" :position="position" />
                </div>
                <SecondaryButton class="min-h-[44px]" @click="emit('close')">Fermer</SecondaryButton>
            </div>
        </div>
    </Modal>
</template>
