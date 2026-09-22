<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    options: { type: Array, required: true }, // [{ value, label, hint? }]
    placeholder: { type: String, default: 'Rechercher…' },
    // Quand activé, une dernière option "+ Créer « … »" apparaît si la saisie
    // ne correspond à rien : le champ sert alors aussi de point d'entrée pour
    // créer une nouvelle entrée à la volée (ex. un nouveau serviteur), sans
    // dupliquer ce composant pour chaque écran qui en a besoin.
    allowCreate: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'create']);

const query = ref('');
const open = ref(false);
const inputRef = ref(null);
const style = ref({});

const selected = computed(() => props.options.find((o) => o.value === props.modelValue) ?? null);

watch(selected, (s) => {
    if (!open.value) query.value = s?.label ?? '';
}, { immediate: true });

const filtered = computed(() => {
    const q = query.value.trim().toLowerCase();
    const source = q === '' ? props.options : props.options.filter((o) => o.label.toLowerCase().includes(q));

    return source.slice(0, 50);
});

// Positionné en `fixed` et téléporté au <body> : un simple `absolute` se
// retrouve rogné (clippé) dès que ce champ est dans un tableau avec
// `overflow-x-auto` (le navigateur force alors overflow-y à auto aussi),
// invisible sans faire défiler le conteneur — piège classique des menus
// déroulants dans un tableau qui défile.
const MAX_HAUTEUR = 224; // max-h-56

const majPosition = () => {
    if (!inputRef.value) return;

    const rect = inputRef.value.getBoundingClientRect();
    const espaceEnBas = window.innerHeight - rect.bottom;
    const ouvrirVersLeHaut = espaceEnBas < MAX_HAUTEUR && rect.top > espaceEnBas;

    style.value = {
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        ...(ouvrirVersLeHaut
            ? { bottom: `${window.innerHeight - rect.top + 4}px`, top: 'auto' }
            : { top: `${rect.bottom + 4}px`, bottom: 'auto' }),
    };
};

const choisir = (option) => {
    emit('update:modelValue', option.value);
    query.value = option.label;
    open.value = false;
};

const creer = () => {
    const texte = query.value.trim();
    if (texte === '') return;

    emit('update:modelValue', '');
    emit('create', texte);
    open.value = false;
};

const onFocus = () => {
    open.value = true;
    query.value = '';
    majPosition();
    window.addEventListener('scroll', majPosition, true);
    window.addEventListener('resize', majPosition);
};

const fermer = () => {
    open.value = false;
    query.value = selected.value?.label ?? '';
    window.removeEventListener('scroll', majPosition, true);
    window.removeEventListener('resize', majPosition);
};

const onBlur = () => setTimeout(fermer, 150);

onBeforeUnmount(() => {
    window.removeEventListener('scroll', majPosition, true);
    window.removeEventListener('resize', majPosition);
});
</script>

<template>
    <div class="relative">
        <input
            ref="inputRef"
            v-model="query"
            type="text"
            :placeholder="placeholder"
            v-bind="$attrs"
            class="block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
            @focus="onFocus"
            @blur="onBlur"
        />
        <Teleport to="body">
            <ul
                v-if="open"
                class="fixed z-50 max-h-56 overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-neutral-200 dark:bg-neutral-800 dark:ring-neutral-600"
                :style="style"
            >
                <li v-if="filtered.length === 0 && !(allowCreate && query.trim() !== '')" class="px-3 py-2 text-neutral-500 dark:text-neutral-400">Aucun résultat</li>
                <li
                    v-for="option in filtered"
                    :key="option.value"
                    class="cursor-pointer px-3 py-2 text-neutral-900 hover:bg-primary-50 dark:text-neutral-100 dark:hover:bg-primary-900/30"
                    @mousedown.prevent="choisir(option)"
                >
                    {{ option.label }}
                    <span v-if="option.hint" class="text-neutral-500 dark:text-neutral-400"> — {{ option.hint }}</span>
                </li>
                <li
                    v-if="allowCreate && query.trim() !== ''"
                    class="cursor-pointer border-t border-neutral-100 px-3 py-2 font-medium text-primary-light hover:bg-primary-50 dark:border-neutral-700 dark:hover:bg-primary-900/30"
                    @mousedown.prevent="creer"
                >
                    <slot name="create" :query="query.trim()">+ Créer « {{ query.trim() }} »</slot>
                </li>
            </ul>
        </Teleport>
    </div>
</template>
