import { computed, ref } from 'vue';

/**
 * Comparaison « humaine » : nombres numériquement, booléens (faux < vrai),
 * texte selon l'ordre alphabétique français sans tenir compte de la casse ni
 * des accents ; les valeurs vides vont toujours en fin de liste.
 */
export function comparerValeurs(av, bv, dir = 1) {
    const vide = (v) => v == null || v === '';
    if (vide(av) && vide(bv)) return 0;
    if (vide(av)) return 1;
    if (vide(bv)) return -1;

    if (typeof av === 'boolean' || typeof bv === 'boolean') {
        return (Number(av) - Number(bv)) * dir;
    }

    if (typeof av === 'number' && typeof bv === 'number') {
        return (av - bv) * dir;
    }

    return av.toString().localeCompare(bv.toString(), 'fr', { sensitivity: 'base', numeric: true }) * dir;
}

/**
 * Tri côté client au clic sur un en-tête de colonne. `source` doit être une
 * fonction retournant le tableau à trier (déjà filtré/recherché en amont si
 * besoin). `accesseurs` (optionnel) associe une clé de tri à une fonction
 * `(ligne) => valeur` quand la valeur triée diffère du champ brut (ex. libellé
 * d'un statut plutôt que sa valeur technique). Le tri est stable : à valeur
 * égale, l'ordre d'origine (celui du serveur) est conservé.
 */
export function useTableSort(source, defaultKey = null, defaultDirection = 'asc', accesseurs = {}) {
    const sortKey = ref(defaultKey);
    const sortDirection = ref(defaultDirection);

    const toggleSort = (key) => {
        if (sortKey.value === key) {
            sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
        } else {
            sortKey.value = key;
            sortDirection.value = 'asc';
        }
    };

    const setSort = (key, direction = 'asc') => {
        sortKey.value = key || null;
        sortDirection.value = direction === 'desc' ? 'desc' : 'asc';
    };

    const valeur = (item, key) => {
        const accesseur = typeof accesseurs === 'function' ? accesseurs()[key] : accesseurs[key];
        return accesseur ? accesseur(item) : item[key];
    };

    const sorted = computed(() => {
        const items = source();
        if (!sortKey.value) return items;

        const dir = sortDirection.value === 'asc' ? 1 : -1;
        const key = sortKey.value;

        return [...items].sort((a, b) => comparerValeurs(valeur(a, key), valeur(b, key), dir));
    });

    return { sortKey, sortDirection, toggleSort, setSort, sorted };
}
