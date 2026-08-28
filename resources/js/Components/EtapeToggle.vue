<script setup>
import Badge from '@/Components/Badge.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    servantId: { type: [Number, String], required: true },
    workflowStepId: { type: [Number, String], default: null },
    termine: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const basculer = () => {
    if (props.disabled || !props.workflowStepId) return;

    router.patch(route('servants.workflow.update', [props.servantId, props.workflowStepId]), {
        statut: props.termine ? 'en_attente' : 'termine',
    }, { preserveScroll: true });
};
</script>

<template>
    <button
        type="button"
        :disabled="disabled || !workflowStepId"
        class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
        :class="disabled || !workflowStepId ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
        @click="basculer"
    >
        <Badge :variant="termine ? 'success' : 'neutral'">{{ termine ? 'Oui' : 'Non' }}</Badge>
    </button>
</template>
