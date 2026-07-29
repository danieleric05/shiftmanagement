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
    success: 'bg-success-50 text-success-700 ring-success/20',
    warning: 'bg-warning-50 text-amber-700 ring-warning/30',
    danger: 'bg-danger-50 text-danger ring-danger/20',
    info: 'bg-primary-50 text-primary-light ring-primary-light/30',
    neutral: 'bg-neutral-100 text-neutral-600 ring-neutral-300',
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
