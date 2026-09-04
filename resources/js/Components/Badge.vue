<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'neutral',
        validator: (v) => ['success', 'warning', 'danger', 'info', 'neutral'].includes(v),
    },
});

const variantClasses = {
    success: 'bg-success-50 text-success-700 ring-success/20 dark:bg-success-900/30 dark:text-success-300 dark:ring-success-700/40',
    warning: 'bg-warning-50 text-warning-700 ring-warning/30 dark:bg-warning-900/30 dark:text-warning-300 dark:ring-warning-700/40',
    danger: 'bg-danger-50 text-danger ring-danger/20 dark:bg-danger-900/30 dark:text-danger-300 dark:ring-danger-700/40',
    info: 'bg-primary-50 text-primary-light ring-primary-light/30 dark:bg-primary-900/30 dark:text-primary-300 dark:ring-primary-700/40',
    neutral: 'bg-neutral-100 text-neutral-600 ring-neutral-300 dark:bg-neutral-700 dark:text-neutral-300 dark:ring-neutral-600',
};

const classes = computed(() => variantClasses[props.variant]);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="classes"
    >
        <slot />
    </span>
</template>
