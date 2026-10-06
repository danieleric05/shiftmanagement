<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    // Uniquement les pieux (type "pieu") de l'organisation : [{ id, nom }]
    unites: { type: Array, required: true },
    // Rattachement actuel à un district/une mission (import) : affiché comme
    // option désactivée pour que la fiche reste modifiable sans le changer.
    uniteActuelle: { type: Object, default: null },
});

const emit = defineEmits(['update:modelValue']);

const valeur = computed({
    get: () => (props.modelValue === '' || props.modelValue === undefined ? null : props.modelValue),
    set: (value) => emit('update:modelValue', value),
});

const libelleType = { district: 'district', mission: 'mission' };
</script>

<template>
    <select v-model="valeur" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100">
        <option :value="null">—</option>
        <option v-if="uniteActuelle" :value="uniteActuelle.id" disabled>
            {{ uniteActuelle.nom }} ({{ libelleType[uniteActuelle.type] ?? uniteActuelle.type }} — rattachement actuel)
        </option>
        <option v-for="p in unites" :key="p.id" :value="p.id">{{ p.nom }}</option>
    </select>
</template>
