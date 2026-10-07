<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { AlertTriangle, CalendarClock, Clock, X } from '@lucide/vue';
import { useLicenceCountdown } from '@/composables/useLicenceCountdown';

/**
 * Compte à rebours de la licence (Conseil du Temple uniquement, données
 * fournies par HandleInertiaRequests). Le texte se met à jour chaque minute ;
 * la région polie n'annonce qu'un changement de niveau (passage d'un seuil)
 * ou le masquage du bandeau, jamais chaque minute.
 *
 * Bouton « Masquer » : cache le bandeau jusqu'à la fin de la journée locale
 * (date du jour mémorisée dans localStorage, par organisation et par
 * utilisateur). Il réapparaît le lendemain. Le niveau « urgent » (≤ 7 jours)
 * n'est pas masquable. Si localStorage est indisponible, le bandeau reste
 * simplement visible.
 */
const props = defineProps({
    compteARebours: { type: Object, required: true },
});

const { maintenant, niveau, decompte, dateLisible } = useLicenceCountdown(() => props.compteARebours.expiresAtIso);

const styles = {
    info: { libelle: 'Information', classes: 'border-neutral-100 bg-neutral-50 text-neutral-700 dark:border-neutral-700 dark:bg-neutral-900/60 dark:text-neutral-300', icone: CalendarClock },
    attention: { libelle: 'Attention', classes: 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-800 dark:bg-warning-900/30 dark:text-warning-200', icone: Clock },
    urgent: { libelle: 'Urgent', classes: 'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-800 dark:bg-danger-900/30 dark:text-danger-200', icone: AlertTriangle },
    expire: { libelle: 'Urgent', classes: 'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-800 dark:bg-danger-900/30 dark:text-danger-200', icone: AlertTriangle },
};
const style = computed(() => styles[niveau.value]);

// --- Masquage pour la journée -------------------------------------------
const page = usePage();
const cle = computed(() => {
    const user = page.props.auth?.user;
    return `licence-bandeau-masque:${user?.organisation_id ?? 'aucune'}:${user?.id ?? 'anonyme'}`;
});

// Date locale du navigateur (AAAA-MM-JJ), pas la date UTC.
const dateLocale = (ms) => {
    const d = new Date(ms);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

const lire = () => {
    try {
        return window.localStorage.getItem(cle.value);
    } catch {
        return null;
    }
};

// Lu de façon synchrone dès le montage : pas d'apparition puis disparition.
const masqueLe = ref(lire());

const masquable = computed(() => niveau.value === 'info' || niveau.value === 'attention');
// `maintenant` avance chaque minute : le bandeau revient seul après minuit.
const masque = computed(() => masquable.value && masqueLe.value === dateLocale(maintenant.value));

const annonce = ref('');

const masquer = async () => {
    const jour = dateLocale(Date.now());
    try {
        window.localStorage.setItem(cle.value, jour);
    } catch {
        // Stockage indisponible : on masque tout de même pour cette page.
    }
    masqueLe.value = jour;
    annonce.value = 'Bandeau de licence masqué jusqu’à demain.';

    // Le bouton disparaît : le focus va au contenu principal plutôt que se perdre.
    await nextTick();
    const main = document.querySelector('main');
    if (main) {
        if (!main.hasAttribute('tabindex')) main.setAttribute('tabindex', '-1');
        main.focus({ preventScroll: true });
    }
};

// Annonce aux lecteurs d'écran uniquement au passage d'un seuil.
watch(niveau, (nouveau) => {
    annonce.value = nouveau === 'expire'
        ? 'La licence vient d’expirer. Rechargez la page.'
        : `${styles[nouveau].libelle} : la licence expire dans ${decompte.value}.`;
});
</script>

<template>
    <div>
        <div v-if="!masque" class="border-b px-4 py-2 text-xs sm:text-sm lg:px-8" :class="style.classes">
            <div class="flex items-center gap-3">
                <p class="flex min-w-0 flex-1 flex-wrap items-center gap-x-2 gap-y-0.5">
                    <component :is="style.icone" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span class="font-semibold uppercase tracking-wide">{{ style.libelle }}</span>
                    <template v-if="niveau === 'expire'">
                        <span>La licence a expiré le {{ dateLisible }}. Rechargez la page.</span>
                    </template>
                    <template v-else>
                        <span>Licence valable jusqu’au <strong class="font-semibold">{{ dateLisible }}</strong></span>
                        <span aria-hidden="true">—</span>
                        <span>temps restant : <strong class="font-semibold tabular-nums">{{ decompte }}</strong></span>
                    </template>
                </p>
                <button
                    v-if="masquable"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1 rounded-md px-2 py-1 font-medium underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light focus-visible:ring-offset-1"
                    aria-label="Masquer le bandeau de licence jusqu’à demain"
                    title="Masquer jusqu’à demain"
                    @click="masquer"
                >
                    <X class="h-4 w-4" aria-hidden="true" />
                    <span class="hidden sm:inline">Masquer</span>
                </button>
            </div>
        </div>
        <div role="status" aria-live="polite" class="sr-only">{{ annonce }}</div>
    </div>
</template>
