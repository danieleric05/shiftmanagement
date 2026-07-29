<script setup>
defineProps({
    type: {
        type: String,
        default: 'button',
    },
});
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center gap-1.5 rounded-lg border border-primary-light bg-white px-4 py-2.5 text-sm font-medium text-primary transition duration-150 ease-in-out hover:bg-primary-50 focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2 disabled:cursor-not-allowed disabled:border-neutral-300 disabled:text-neutral-600"
    >
        <slot />
    </button>
</template>
