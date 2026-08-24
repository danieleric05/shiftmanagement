import { computed, ref } from 'vue';

function normaliser(valeur) {
    return (valeur ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/**
 * Filtrage texte côté client pour les tableaux non paginés : `source` doit
 * être une fonction retournant le tableau à filtrer (ex. `() => props.items`)
 * pour rester réactif aux props Inertia.
 */
export function useTableSearch(source, champs) {
    const recherche = ref('');

    const resultats = computed(() => {
        const items = source();
        const q = normaliser(recherche.value).trim();
        if (!q) return items;

        return items.filter((item) => champs.some((champ) => normaliser(item[champ]).includes(q)));
    });

    return { recherche, resultats };
}
