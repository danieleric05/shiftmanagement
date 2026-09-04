<script setup>
import InputLabel from '@/Components/InputLabel.vue';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [Number, String], default: null },
    unites: { type: Array, required: true }, // [{ id, nom, type, parent_id }]
});

const emit = defineEmits(['update:modelValue']);

const parId = computed(() => new Map(props.unites.map((u) => [u.id, u])));

const cheminDepuis = (id) => {
    const chemin = [];
    let courant = id ? parId.value.get(id) : null;

    while (courant) {
        chemin.unshift(courant);
        courant = courant.parent_id ? parId.value.get(courant.parent_id) : null;
    }

    return chemin;
};

const missionId = ref(null);
const districtId = ref(null);
const pieuId = ref(null);

watch(() => props.modelValue, (value) => {
    const chemin = cheminDepuis(value);
    missionId.value = chemin.find((u) => u.type === 'mission')?.id ?? null;
    districtId.value = chemin.find((u) => u.type === 'district')?.id ?? null;
    pieuId.value = chemin.find((u) => u.type === 'pieu')?.id ?? null;
}, { immediate: true });

const missions = computed(() => props.unites.filter((u) => u.type === 'mission'));
const districts = computed(() => props.unites.filter((u) => u.type === 'district' && (!missionId.value || u.parent_id === missionId.value)));
const pieux = computed(() => props.unites.filter((u) => u.type === 'pieu' && (!districtId.value || u.parent_id === districtId.value)));

const emettre = () => emit('update:modelValue', pieuId.value || districtId.value || missionId.value || null);

const onMission = () => { districtId.value = null; pieuId.value = null; emettre(); };
const onDistrict = () => { pieuId.value = null; emettre(); };
</script>

<template>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div>
            <InputLabel value="Mission (optionnel)" />
            <select v-model="missionId" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100" @change="onMission">
                <option :value="null">—</option>
                <option v-for="m in missions" :key="m.id" :value="m.id">{{ m.nom }}</option>
            </select>
        </div>
        <div>
            <InputLabel value="District (optionnel)" />
            <select v-model="districtId" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100" @change="onDistrict">
                <option :value="null">—</option>
                <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.nom }}</option>
            </select>
        </div>
        <div>
            <InputLabel value="Pieu (optionnel)" />
            <select v-model="pieuId" class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100" @change="emettre">
                <option :value="null">—</option>
                <option v-for="p in pieux" :key="p.id" :value="p.id">{{ p.nom }}</option>
            </select>
        </div>
    </div>
</template>
