<script setup>
import { useTheme } from '@/composables/useTheme';
import { Laptop, Moon, Sun } from '@lucide/vue';

const { theme, definirTheme } = useTheme();

const options = [
    { value: 'light', label: 'Clair', icon: Sun },
    { value: 'dark', label: 'Sombre', icon: Moon },
    { value: 'system', label: 'Système', icon: Laptop },
];
</script>

<template>
    <div class="inline-flex items-center gap-0.5 rounded-full bg-neutral-100 p-0.5 dark:bg-neutral-700">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            class="rounded-full p-1.5 transition"
            :class="theme === option.value
                ? 'bg-white text-primary shadow-sm dark:bg-neutral-900 dark:text-primary-light'
                : 'text-neutral-500 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100'"
            :aria-label="`Thème ${option.label}`"
            :aria-pressed="theme === option.value"
            :title="`Thème ${option.label}`"
            @click="definirTheme(option.value)"
        >
            <component :is="option.icon" class="h-4 w-4" />
        </button>
    </div>
</template>
