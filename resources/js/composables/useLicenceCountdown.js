import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';

const MINUTE = 60 * 1000;
const JOUR = 24 * 60 * MINUTE;

const pluriel = (n, mot) => `${n} ${mot}${n > 1 ? 's' : ''}`;

/**
 * Compte à rebours d'une licence datée, partagé par le bandeau
 * (LicenceCountdownBanner) et la page Paramètres → Licence.
 *
 * Le serveur fournit la date d'expiration (ISO 8601) ; le décompte précis se
 * fait avec l'heure du navigateur, recalculé à chaque changement de minute.
 * Niveaux : info (> 60 jours), attention (≤ 60 jours), urgent (≤ 7 jours),
 * expire (échéance passée).
 *
 * @param {import('vue').MaybeRefOrGetter<string>} expiresAtIso
 */
export function useLicenceCountdown(expiresAtIso) {
    const expiration = computed(() => new Date(toValue(expiresAtIso)));
    const maintenant = ref(Date.now());

    let minuteur = null;
    let alignement = null;
    const tic = () => (maintenant.value = Date.now());

    onMounted(() => {
        tic();
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

    const joursRestants = computed(() => Math.floor(restantMs.value / JOUR));

    const decompte = computed(() => {
        const totalMinutes = Math.floor(restantMs.value / MINUTE);
        const jours = Math.floor(totalMinutes / (24 * 60));
        const heures = Math.floor((totalMinutes % (24 * 60)) / 60);
        const minutes = totalMinutes % 60;
        return `${pluriel(jours, 'jour')}, ${pluriel(heures, 'heure')} et ${pluriel(minutes, 'minute')}`;
    });

    const dateLisible = computed(() => new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeStyle: 'short' }).format(expiration.value));

    return { maintenant, expiration, restantMs, niveau, joursRestants, decompte, dateLisible };
}
