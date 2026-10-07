<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { AlertTriangle, CalendarClock, Clock } from '@lucide/vue';

/**
 * Compte à rebours de la licence (Conseil du Temple uniquement, données
 * fournies par HandleInertiaRequests). Le texte se met à jour chaque minute ;
 * la région role="status" n'annonce qu'un changement de niveau (passage d'un
 * seuil), jamais chaque minute.
 */
const props = defineProps({
    compteARebours: { type: Object, required: true },
});

const MINUTE = 60 * 1000;
const JOUR = 24 * 60 * MINUTE;

const expiration = computed(() => new Date(props.compteARebours.expiresAtIso));
// Heure du navigateur (le serveur fournit joursRestants/niveau comme valeur
// de départ indicative ; le décompte précis se fait ici).
const maintenant = ref(Date.now());

let minuteur = null;
let alignement = null;
const tic = () => (maintenant.value = Date.now());

onMounted(() => {
    // Aligné sur le changement de minute, puis toutes les 60 s.
    alignement = setTimeout(() => {
        tic();
        minuteur = setInterval(tic, MINUTE);
    }, MINUTE - (Date.now() % MINUTE));
});

onBeforeUnmount(() => {
    clearTimeout(alignement);
    clearInterval(minuteur);
});

const restantMs = computed(() => Math.max(0, expiration.value.getTime() - maintenant.value));

const niveau = computed(() => {
    if (restantMs.value <= 0) return 'expire';
    if (restantMs.value <= 7 * JOUR) return 'urgent';
    if (restantMs.value <= 60 * JOUR) return 'attention';
    return 'info';
});

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

const decompte = computed(() => {
    const totalMinutes = Math.floor(restantMs.value / MINUTE);
    const jours = Math.floor(totalMinutes / (24 * 60));
    const heures = Math.floor((totalMinutes % (24 * 60)) / 60);
    const minutes = totalMinutes % 60;
    return `${pluriel(jours, 'jour')}, ${pluriel(heures, 'heure')} et ${pluriel(minutes, 'minute')}`;
});

const dateLisible = computed(() => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(expiration.value));

const styles = {
    info: { libelle: 'Information', classes: 'border-neutral-100 bg-neutral-50 text-neutral-700 dark:border-neutral-700 dark:bg-neutral-900/60 dark:text-neutral-300', icone: CalendarClock },
    attention: { libelle: 'Attention', classes: 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-800 dark:bg-warning-900/30 dark:text-warning-200', icone: Clock },
    urgent: { libelle: 'Urgent', classes: 'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-800 dark:bg-danger-900/30 dark:text-danger-200', icone: AlertTriangle },
    expire: { libelle: 'Urgent', classes: 'border-danger-200 bg-danger-50 text-danger-800 dark:border-danger-800 dark:bg-danger-900/30 dark:text-danger-200', icone: AlertTriangle },
};
const style = computed(() => styles[niveau.value]);

// Annonce aux lecteurs d'écran uniquement au passage d'un seuil.
const annonce = ref('');
watch(niveau, (nouveau) => {
    annonce.value = nouveau === 'expire'
        ? 'La licence vient d’expirer. Rechargez la page.'
        : `${styles[nouveau].libelle} : la licence expire dans ${decompte.value}.`;
});
</script>

<template>
    <div class="border-b px-4 py-2 text-xs sm:text-sm lg:px-8" :class="style.classes">
        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
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
        <div role="status" class="sr-only">{{ annonce }}</div>
    </div>
</template>
