<script setup>
import { ChevronDown, ChevronsUpDown, ChevronUp } from '@lucide/vue';
import { computed } from 'vue';

const props = defineProps({
    label: { type: String, required: true },
    sortKey: { type: String, required: true },
    activeKey: { type: String, default: null },
    direction: { type: String, default: 'asc' },
    // Espacement de la cellule (les tableaux compacts du DataTable utilisent moins de marge).
    cellClass: { type: String, default: 'px-6 py-3' },
    align: { type: String, default: 'start' },
});

const emit = defineEmits(['sort']);

const actif = computed(() => props.activeKey === props.sortKey);

// aria-sort n'est porté que par la colonne effectivement triée (recommandation ARIA APG).
const ariaSort = computed(() => {
    if (!actif.value) return undefined;
    return props.direction === 'desc' ? 'descending' : 'ascending';
});

const aide = computed(() => {
    if (!actif.value) return 'trier par ordre croissant';
    return props.direction === 'asc' ? 'trié par ordre croissant, activer pour trier par ordre décroissant' : 'trié par ordre décroissant, activer pour trier par ordre croissant';
});
</script>

<template>
    <th
        scope="col"
        :aria-sort="ariaSort"
        class="text-xs font-medium uppercase tracking-wider text-neutral-600 dark:text-neutral-400"
        :class="[cellClass, align === 'end' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left']"
    >
        <!-- Contenu optionnel avant le libellé (ex. poignée de déplacement de colonne). -->
        <slot name="avant" />
        <button
            type="button"
            class="-mx-1 inline-flex min-h-[44px] max-w-full items-center gap-1 rounded px-1 text-left uppercase tracking-wider hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:hover:text-neutral-100"
            :class="actif ? 'text-neutral-900 dark:text-neutral-100' : ''"
            @click="emit('sort', sortKey)"
        >
            <span class="min-w-0 break-words">{{ label }}</span>
            <span class="sr-only">, {{ aide }}</span>
            <ChevronUp v-if="actif && direction === 'asc'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            <ChevronDown v-else-if="actif && direction === 'desc'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            <ChevronsUpDown v-else class="h-3.5 w-3.5 shrink-0 text-neutral-400" aria-hidden="true" />
        </button>
    </th>
</template>
