import { computed, ref } from 'vue';

/**
 * Tri au clic sur un en-tête de colonne. `source` doit être une fonction
 * retournant le tableau à trier (déjà filtré/recherché en amont si besoin).
 */
export function useTableSort(source, defaultKey = null, defaultDirection = 'asc') {
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

    const sorted = computed(() => {
        const items = source();
        if (!sortKey.value) return items;

        const dir = sortDirection.value === 'asc' ? 1 : -1;

        return [...items].sort((a, b) => {
            const av = a[sortKey.value];
            const bv = b[sortKey.value];

            if (av == null && bv == null) return 0;
            if (av == null) return 1;
            if (bv == null) return -1;

            if (typeof av === 'number' && typeof bv === 'number') {
                return (av - bv) * dir;
            }

            return av.toString().localeCompare(bv.toString(), 'fr', { sensitivity: 'base' }) * dir;
        });
    });

    return { sortKey, sortDirection, toggleSort, sorted };
}
