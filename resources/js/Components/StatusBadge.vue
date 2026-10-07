<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Badge.vue';

const props = defineProps({
    statut: {
        type: String,
        required: true,
    },
    // Certaines valeurs de statut sont partagées entre plusieurs domaines
    // (ex. "actif" existe pour un Shift, une affectation et une personne)
    // mais doivent s'afficher différemment selon le contexte.
    domain: {
        type: String,
        default: null,
    },
});

// Statut d'une PERSONNE (servant ou titulaire d'un compte utilisateur) :
// la valeur technique « actif » signifie que la personne n'est plus
// « nouvelle », elle s'affiche donc « Ancien » (le libellé « Actif » reste
// réservé aux Shifts).
const statutsPersonne = {
    en_formation: { label: 'Nouveau', variant: 'info' },
    actif: { label: 'Ancien', variant: 'success' },
};

const surchargesParDomaine = {
    servant: {
        ...statutsPersonne,
        suspendu: { label: 'Relevé', variant: 'neutral' },
    },
    utilisateur: statutsPersonne,
};

const map = {
    actif: { label: 'Actif', variant: 'success' },
    valide: { label: 'Validé', variant: 'success' },
    validee: { label: 'Validée', variant: 'success' },
    termine: { label: 'Terminé', variant: 'info' },
    en_attente: { label: 'En attente', variant: 'warning' },
    en_cours: { label: 'En cours', variant: 'warning' },
    recommande: { label: 'Recommandé', variant: 'warning' },
    en_formation: { label: 'Nouveau', variant: 'info' },
    inactif: { label: 'Inactif', variant: 'neutral' },
    ignore: { label: 'Ignoré', variant: 'neutral' },
    suspendu: { label: 'Suspendu', variant: 'neutral' },
    refuse: { label: 'Refusé', variant: 'danger' },
    rejetee: { label: 'Rejetée', variant: 'danger' },
    retire: { label: 'Permutant', variant: 'danger' },
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
