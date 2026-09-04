<script setup>
import { Search } from '@lucide/vue';

defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Rechercher…' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative w-full sm:max-w-xs">
        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-600 dark:text-neutral-400" />
        <input
            :value="modelValue"
            type="text"
            :placeholder="placeholder"
            class="w-full rounded-lg border-neutral-300 py-2 pl-9 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100 dark:placeholder-neutral-500"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
