<script setup>
import Badge from '@/Components/Badge.vue';
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    servantId: { type: [Number, String], required: true },
    workflowStepId: { type: [Number, String], default: null },
    termine: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

// Rôle « Autres » (lecture seule) : l'étape est affichée mais jamais basculable.
const page = usePage();
const inactif = computed(() => props.disabled || Boolean(page.props.auth?.lectureSeule) || !props.workflowStepId);

const basculer = () => {
    if (inactif.value) return;

    router.patch(route('servants.workflow.update', [props.servantId, props.workflowStepId]), {
        statut: props.termine ? 'en_attente' : 'termine',
    }, { preserveScroll: true });
};
</script>

<template>
    <button
        type="button"
        :disabled="inactif"
        class="rounded-full focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
        :class="inactif ? 'cursor-not-allowed opacity-70' : 'cursor-pointer'"
        @click="basculer"
    >
        <Badge :variant="termine ? 'success' : 'neutral'">{{ termine ? 'Oui' : 'Non' }}</Badge>
    </button>
</template>
