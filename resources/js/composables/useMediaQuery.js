import { onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Suivi réactif d'une media query (ex. '(min-width: 1024px)' pour le point
 * de rupture `lg` de Tailwind). Valeur initiale calculée immédiatement côté
 * navigateur pour éviter un premier rendu dans le mauvais mode.
 */
export function useMediaQuery(requete) {
    const liste = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia(requete) : null;
    const correspond = ref(liste ? liste.matches : true);

    const maj = (e) => (correspond.value = e.matches);

    onMounted(() => {
        if (!liste) return;
        correspond.value = liste.matches;
        liste.addEventListener('change', maj);
    });

    onBeforeUnmount(() => liste?.removeEventListener('change', maj));

    return correspond;
}

/** Point de rupture `lg` de Tailwind (1024 px). */
export const REQUETE_LG = '(min-width: 1024px)';
