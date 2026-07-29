<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    statut: {
        type: String,
        required: true,
    },
});

const map = {
    actif: { label: 'Actif', variant: 'success' },
    valide: { label: 'Validé', variant: 'success' },
    validee: { label: 'Validée', variant: 'success' },
    termine: { label: 'Terminé', variant: 'info' },
    en_attente: { label: 'En attente', variant: 'warning' },
    en_cours: { label: 'En cours', variant: 'warning' },
    recommande: { label: 'Recommandé', variant: 'warning' },
    en_formation: { label: 'En formation', variant: 'info' },
    inactif: { label: 'Inactif', variant: 'neutral' },
    ignore: { label: 'Ignoré', variant: 'neutral' },
    suspendu: { label: 'Suspendu', variant: 'neutral' },
    refuse: { label: 'Refusé', variant: 'danger' },
    rejetee: { label: 'Rejetée', variant: 'danger' },
    retire: { label: 'Retiré', variant: 'danger' },
};

const entry = computed(() => map[props.statut] ?? { label: props.statut, variant: 'neutral' });
</script>

<template>
    <Badge :variant="entry.variant">{{ entry.label }}</Badge>
</template>
