<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: {
        type: String,
        required: true,
    },
    active: {
        type: Boolean,
    },
});

const classes = computed(() =>
    props.active
        ? 'inline-flex items-center px-1 pt-1 border-b-2 border-primary-light text-sm font-medium leading-5 text-neutral-900 focus:outline-none focus:border-primary transition duration-150 ease-in-out dark:text-neutral-100'
        : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-neutral-600 hover:text-neutral-700 hover:border-neutral-300 focus:outline-none focus:text-neutral-700 focus:border-neutral-300 transition duration-150 ease-in-out dark:text-neutral-400 dark:hover:text-neutral-200 dark:hover:border-neutral-600',
);
</script>

<template>
    <Link :href="href" :class="classes">
        <slot />
    </Link>
</template>
