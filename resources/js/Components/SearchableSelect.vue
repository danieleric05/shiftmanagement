<script setup>
import { ref, computed, watch } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    options: { type: Array, required: true }, // [{ value, label }]
    placeholder: { type: String, default: 'Rechercher…' },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const open = ref(false);

const selected = computed(() => props.options.find((o) => o.value === props.modelValue) ?? null);

watch(selected, (s) => {
    if (!open.value) query.value = s?.label ?? '';
}, { immediate: true });

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const source = q === '' ? props.options : props.options.filter((o) => o.label.toLowerCase().includes(q));

    return source.slice(0, 50);
});

const choisir = (option) => {
    emit('update:modelValue', option.value);
    query.value = option.label;
    open.value = false;
};

const onFocus = () => {
    open.value = true;
    query.value = '';
};

const onBlur = () => {
    setTimeout(() => {
        open.value = false;
        query.value = selected.value?.label ?? '';
    }, 150);
};
</script>

<template>
    <div class="relative">
        <input
            v-model="query"
            type="text"
            :placeholder="placeholder"
            v-bind="$attrs"
            class="block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
            @focus="onFocus"
            @blur="onBlur"
        />
        <ul
            v-if="open"
            class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-neutral-200"
        >
            <li v-if="filtered.length === 0" class="px-3 py-2 text-neutral-500">Aucun résultat</li>
            <li
                v-for="option in filtered"
                :key="option.value"
                class="cursor-pointer px-3 py-2 hover:bg-primary-50"
                @mousedown.prevent="choisir(option)"
            >
                {{ option.label }}
            </li>
        </ul>
    </div>
</template>
