<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    statut: {
        type: String,
        required: true,
    },
    // Certaines valeurs de statut sont partagées entre plusieurs domaines
    // (ex. "suspendu" existe à la fois pour un compte utilisateur et pour
    // un servant) mais doivent s'afficher différemment selon le contexte.
    domain: {
        type: String,
        default: null,
    },
});

const surchargesParDomaine = {
    servant: {
        suspendu: { label: 'Relevé', variant: 'neutral' },
    },
};

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
    nouveau: { label: 'Nouveau', variant: 'neutral' },
    appele: { label: 'Appelé', variant: 'warning' },
    entretien_planifie: { label: 'Entretien planifié', variant: 'info' },
    entretien_realise: { label: 'Entretien réalisé', variant: 'info' },
    converti: { label: 'Converti', variant: 'success' },
    abandonne: { label: 'Abandonné', variant: 'danger' },
    planifie: { label: 'Planifié', variant: 'info' },
    realise: { label: 'Réalisé', variant: 'success' },
    annule: { label: 'Annulé', variant: 'danger' },
    traitee: { label: 'Traitée', variant: 'success' },
};

const entry = computed(() => {
    const surcharge = props.domain ? surchargesParDomaine[props.domain]?.[props.statut] : null;

    return surcharge ?? map[props.statut] ?? { label: props.statut, variant: 'neutral' };
});
</script>

<template>
    <Badge :variant="entry.variant">{{ entry.label }}</Badge>
</template>
