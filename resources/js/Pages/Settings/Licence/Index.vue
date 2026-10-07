<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AlertTriangle, CalendarClock, CheckCircle2, Clock, Infinity as InfinityIcon } from '@lucide/vue';
import { useLicenceCountdown } from '@/composables/useLicenceCountdown';

/**
 * Paramètres → Licence : consultation en lecture seule de la licence de
 * l'organisation (Conseil du Temple / Super Administrateur). Le décompte
 * réutilise la logique du bandeau (useLicenceCountdown), mis à jour chaque minute.
 */
const props = defineProps({
    licence: { type: Object, required: true },
});

const datee = computed(() => Boolean(props.licence.expiresAtIso));

// Sans date d'expiration, le composable reçoit une date fictive non affichée.
const { niveau, joursRestants, decompte, dateLisible } = useLicenceCountdown(() => props.licence.expiresAtIso ?? '9999-12-31T00:00:00Z');

// État calculé en direct (passage d'un seuil sans recharger), le serveur
// fournissant l'état initial.
const etat = computed(() => {
    if (!datee.value) return 'sans_date';
    if (niveau.value === 'expire') return 'expiree';
    if (niveau.value === 'urgent' || niveau.value === 'attention') return 'expire_bientot';
    return 'valide';
});

const presentations = {
    valide: { libelle: 'Valide', variant: 'success', icone: CheckCircle2 },
    expire_bientot: { libelle: 'Expire bientôt', variant: 'warning', icone: Clock },
    expiree: { libelle: 'Expirée', variant: 'danger', icone: AlertTriangle },
    sans_date: { libelle: "Sans date d'expiration", variant: 'neutral', icone: InfinityIcon },
};
const presentation = computed(() => presentations[etat.value]);

const niveaux = {
    info: { libelle: 'Information', detail: "plus de 60 jours avant l'expiration", variant: 'info' },
    attention: { libelle: 'Attention', detail: "60 jours ou moins avant l'expiration", variant: 'warning' },
    urgent: { libelle: 'Urgent', detail: "7 jours ou moins avant l'expiration", variant: 'danger' },
    expire: { libelle: 'Expirée', detail: 'la licence a expiré : application en lecture seule', variant: 'danger' },
};
const niveauAffiche = computed(() => (datee.value ? niveaux[niveau.value] : null));

const joursTexte = computed(() => `${joursRestants.value} jour${joursRestants.value > 1 ? 's' : ''}`);
</script>

<template>
    <Head title="Licence" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Paramètres', href: route('settings.index') }, { label: 'Licence' }]">
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                Paramètres — Licence
            </h2>
        </template>

        <div class="mx-auto max-w-2xl space-y-6">
            <section aria-labelledby="licence-titre" class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <h3 id="licence-titre" class="flex items-center gap-2 text-lg font-medium text-neutral-900 dark:text-neutral-100">
                    <CalendarClock class="h-5 w-5 text-primary dark:text-primary-light" aria-hidden="true" />
                    Licence de l'organisation
                </h3>

                <dl class="mt-4 divide-y divide-neutral-100 text-sm dark:divide-neutral-700">
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="font-medium text-neutral-600 dark:text-neutral-400">Organisation</dt>
                        <dd class="text-neutral-900 dark:text-neutral-100 sm:col-span-2">{{ licence.organisation }}</dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="font-medium text-neutral-600 dark:text-neutral-400">État</dt>
                        <dd class="sm:col-span-2">
                            <Badge :variant="presentation.variant">
                                <component :is="presentation.icone" class="h-3.5 w-3.5" aria-hidden="true" />
                                {{ presentation.libelle }}
                            </Badge>
                        </dd>
                    </div>
                    <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                        <dt class="font-medium text-neutral-600 dark:text-neutral-400">Date d'expiration</dt>
                        <dd class="text-neutral-900 dark:text-neutral-100 sm:col-span-2">
                            {{ datee ? dateLisible : "Aucune : la licence n'a pas de date d'expiration." }}
                        </dd>
                    </div>
                    <template v-if="datee">
                        <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt class="font-medium text-neutral-600 dark:text-neutral-400">Temps restant</dt>
                            <dd class="text-neutral-900 dark:text-neutral-100 sm:col-span-2">
                                <template v-if="etat === 'expiree'">Aucun : la licence a expiré.</template>
                                <template v-else>
                                    <strong class="font-semibold tabular-nums">{{ joursTexte }}</strong>
                                    <span class="block text-neutral-600 tabular-nums dark:text-neutral-400">soit {{ decompte }} (mis à jour chaque minute)</span>
                                </template>
                            </dd>
                        </div>
                        <div class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3 sm:gap-4">
                            <dt class="font-medium text-neutral-600 dark:text-neutral-400">Niveau d'alerte</dt>
                            <dd class="sm:col-span-2">
                                <Badge :variant="niveauAffiche.variant">{{ niveauAffiche.libelle }}</Badge>
                                <span class="ml-2 text-neutral-600 dark:text-neutral-400">{{ niveauAffiche.detail }}</span>
                            </dd>
                        </div>
                    </template>
                </dl>
            </section>

            <p class="rounded-xl bg-neutral-50 p-4 text-sm text-neutral-700 ring-1 ring-neutral-100 dark:bg-neutral-900/60 dark:text-neutral-300 dark:ring-neutral-700">
                Pour renouveler la licence, contactez le propriétaire de la plateforme.
            </p>
        </div>
    </AuthenticatedLayout>
</template>
