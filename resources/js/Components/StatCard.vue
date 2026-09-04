<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    icon: { type: [Object, Function], required: true },
    tone: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'success', 'warning', 'danger'].includes(v),
    },
});

const toneClasses = {
    primary: 'bg-primary-50 text-primary dark:bg-primary-900/30 dark:text-primary-300',
    success: 'bg-success-50 text-success-700 dark:bg-success-900/30 dark:text-success-300',
    warning: 'bg-warning-50 text-warning-700 dark:bg-warning-900/30 dark:text-warning-300',
    danger: 'bg-danger-50 text-danger dark:bg-danger-900/30 dark:text-danger-300',
};
</script>

<template>
    <div class="rounded-xl bg-white p-5 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
        <div class="flex items-start justify-between">
            <div>
                <p class="text-sm text-neutral-600 dark:text-neutral-400">{{ label }}</p>
                <p class="mt-1.5 text-3xl font-bold text-neutral-900 dark:text-neutral-100">{{ value }}</p>
                <p v-if="hint" class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">{{ hint }}</p>
            </div>
            <div class="rounded-lg p-2.5" :class="toneClasses[tone]">
                <component :is="icon" class="h-5 w-5" stroke-width="2" />
            </div>
        </div>
    </div>
</template>
