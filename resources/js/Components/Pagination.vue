<script setup>
import { libellePagination } from '@/composables/usePagination';
import { Link } from '@inertiajs/vue3';

/**
 * Pagination d'un paginateur Laravel (`links`). Les URL portent déjà la
 * recherche, les filtres et le tri (withQueryString côté serveur).
 */
defineProps({
    links: { type: Array, default: () => [] },
    label: { type: String, default: 'Pagination' },
});
</script>

<template>
    <nav v-if="links.length > 3" :aria-label="label" class="flex flex-wrap justify-center gap-1">
        <template v-for="link in links" :key="link.label">
            <span
                v-if="!link.url"
                class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md px-3 text-sm text-neutral-400"
                v-html="libellePagination(link.label)"
            />
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                preserve-state
                :aria-current="link.active ? 'page' : undefined"
                class="inline-flex min-h-[44px] min-w-[44px] items-center justify-center rounded-md px-3 text-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                :class="link.active ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700'"
                v-html="libellePagination(link.label)"
            />
        </template>
    </nav>
</template>
