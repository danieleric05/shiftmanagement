<script setup>
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';

defineProps({
    // [{ label, href }] — le dernier élément est toujours la page courante
    // (non cliquable), avec ou sans href fourni.
    items: { type: Array, required: true },
});
</script>

<template>
    <nav aria-label="Fil d'Ariane" class="flex items-center gap-1.5 overflow-x-auto whitespace-nowrap text-xs text-neutral-500">
        <template v-for="(item, index) in items" :key="index">
            <ChevronRight v-if="index > 0" class="h-3.5 w-3.5 shrink-0 text-neutral-300" />
            <Link
                v-if="item.href && index < items.length - 1"
                :href="item.href"
                class="shrink-0 hover:text-neutral-700 hover:underline"
            >
                {{ item.label }}
            </Link>
            <span v-else class="shrink-0" :class="index === items.length - 1 ? 'font-medium text-neutral-700' : ''">
                {{ item.label }}
            </span>
        </template>
    </nav>
</template>
