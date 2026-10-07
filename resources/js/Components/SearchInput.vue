<script setup>
import { Search, X } from '@lucide/vue';
import { nextTick, ref } from 'vue';

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Rechercher…' },
    // Libellé accessible : le placeholder seul n'est pas annoncé de façon
    // fiable par les lecteurs d'écran.
    label: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const champ = ref(null);

// Après effacement, le bouton disparaît (v-if) : on redonne le focus au
// champ pour ne pas le perdre sur le document.
function effacer() {
    emit('update:modelValue', '');
    nextTick(() => champ.value?.focus());
}
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <Search aria-hidden="true" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-600 dark:text-neutral-400" />
        <input
            ref="champ"
            :value="modelValue"
            type="search"
            :placeholder="placeholder"
            :aria-label="label ?? placeholder"
            class="min-h-[44px] w-full rounded-lg border-neutral-300 py-2 pl-9 pr-9 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500 [&::-webkit-search-cancel-button]:hidden"
            @input="$emit('update:modelValue', $event.target.value)"
            @keydown.esc="modelValue && $emit('update:modelValue', '')"
        />
        <button
            v-if="modelValue"
            type="button"
            aria-label="Effacer la recherche"
            class="absolute right-0.5 top-1/2 inline-flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded text-neutral-500 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-400 dark:hover:text-neutral-100"
            @click="effacer"
        >
            <X aria-hidden="true" class="h-4 w-4" />
        </button>
    </div>
</template>
