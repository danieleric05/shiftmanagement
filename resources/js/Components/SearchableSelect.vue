<script setup>
import { ref, computed, watch, onBeforeUnmount, onMounted, nextTick, useId } from 'vue';

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

// Navigation au clavier (motif ARIA « combobox » + « listbox ») : le focus
// reste dans le champ, l'option active est désignée par aria-activedescendant.
const idBase = useId();
const idListe = `${idBase}-liste`;
const idOption = (index) => `${idBase}-option-${index}`;
const actif = ref(-1);

const peutCreer = computed(() => props.allowCreate && query.value.trim() !== '');
// Options navigables : les résultats filtrés, puis l'éventuelle création.
const nbOptions = computed(() => filtered.value.length + (peutCreer.value ? 1 : 0));

// Pendant la saisie, le premier résultat devient actif (Entrée le choisit) ;
// champ vide : aucune option active, pour qu'Entrée ne choisisse rien par mégarde.
watch([filtered, peutCreer], () => {
    actif.value = open.value && nbOptions.value > 0 && query.value.trim() !== '' ? 0 : -1;
});

const defilerVersActif = () => {
    nextTick(() => {
        if (actif.value < 0) return;
        document.getElementById(idOption(actif.value))?.scrollIntoView({ block: 'nearest' });
    });
};

const activer = (index) => {
    if (nbOptions.value === 0) {
        actif.value = -1;
        return;
    }
    actif.value = Math.max(0, Math.min(index, nbOptions.value - 1));
    defilerVersActif();
};

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
    actif.value = -1;
};

const creer = () => {
    const texte = query.value.trim();
    if (texte === '') return;

    emit('update:modelValue', '');
    emit('create', texte);
    open.value = false;
    actif.value = -1;
};

const ouvrir = () => {
    if (open.value) return;
    open.value = true;
    query.value = '';
    actif.value = -1;
    majPosition();
    window.addEventListener('scroll', majPosition, true);
    window.addEventListener('resize', majPosition);
};

const onFocus = ouvrir;

const fermer = () => {
    open.value = false;
    actif.value = -1;
    query.value = selected.value?.label ?? '';
    window.removeEventListener('scroll', majPosition, true);
    window.removeEventListener('resize', majPosition);
};

const onBlur = () => setTimeout(fermer, 150);

const onKeydown = (e) => {
    switch (e.key) {
        case 'ArrowDown':
            e.preventDefault();
            if (!open.value) {
                ouvrir();
                nextTick(() => activer(0));
            } else {
                activer(actif.value + 1);
            }
            break;
        case 'ArrowUp':
            e.preventDefault();
            if (!open.value) {
                ouvrir();
                nextTick(() => activer(nbOptions.value - 1));
            } else {
                activer(actif.value <= 0 ? 0 : actif.value - 1);
            }
            break;
        case 'Home':
            if (!open.value) return;
            e.preventDefault();
            activer(0);
            break;
        case 'End':
            if (!open.value) return;
            e.preventDefault();
            activer(nbOptions.value - 1);
            break;
        case 'Enter':
            if (!open.value || actif.value < 0) return;
            // Empêche la soumission du formulaire englobant.
            e.preventDefault();
            if (actif.value < filtered.value.length) {
                choisir(filtered.value[actif.value]);
            } else if (peutCreer.value) {
                creer();
            }
            break;
        case 'Escape':
            if (!open.value) return;
            // Ne ferme que la liste : ni la fenêtre modale englobante
            // (écouteur sur document), ni le <dialog> natif.
            e.preventDefault();
            e.stopPropagation();
            fermer();
            break;
        case 'Tab':
            if (open.value) fermer();
            break;
    }
};

// Dans une fenêtre modale (<dialog> ouvert par showModal), tout ce qui est
// hors du dialogue est inerte : la liste est alors téléportée dans le
// dialogue lui-même plutôt que dans <body>.
const cibleTeleport = ref('body');
onMounted(() => {
    const dialogue = inputRef.value?.closest('dialog');
    if (dialogue) cibleTeleport.value = dialogue;
});

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
            role="combobox"
            aria-autocomplete="list"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="idListe"
            :aria-activedescendant="open && actif >= 0 ? idOption(actif) : undefined"
            autocomplete="off"
            :placeholder="placeholder"
            v-bind="$attrs"
            class="block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light dark:border-neutral-600 dark:bg-neutral-900 dark:text-neutral-100"
            @focus="onFocus"
            @blur="onBlur"
            @keydown="onKeydown"
        />
        <Teleport :to="cibleTeleport">
            <ul
                v-if="open"
                :id="idListe"
                role="listbox"
                :aria-label="$attrs['aria-label'] || placeholder"
                class="fixed z-50 max-h-56 overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-neutral-200 dark:bg-neutral-800 dark:ring-neutral-600"
                :style="style"
            >
                <li v-if="filtered.length === 0 && !peutCreer" role="presentation" class="px-3 py-2 text-neutral-500 dark:text-neutral-400">Aucun résultat</li>
                <li
                    v-for="(option, index) in filtered"
                    :id="idOption(index)"
                    :key="option.value"
                    role="option"
                    :aria-selected="option.value === modelValue ? 'true' : 'false'"
                    class="cursor-pointer px-3 py-2 text-neutral-900 hover:bg-primary-50 dark:text-neutral-100 dark:hover:bg-primary-900/30"
                    :class="{ 'bg-primary-50 dark:bg-primary-900/30': index === actif }"
                    @mousedown.prevent="choisir(option)"
                    @mousemove="actif = index"
                >
                    {{ option.label }}
                    <span v-if="option.hint" class="text-neutral-500 dark:text-neutral-400"> — {{ option.hint }}</span>
                </li>
                <li
                    v-if="peutCreer"
                    :id="idOption(filtered.length)"
                    role="option"
                    aria-selected="false"
                    class="cursor-pointer border-t border-neutral-100 px-3 py-2 font-medium text-primary-light hover:bg-primary-50 dark:border-neutral-700 dark:hover:bg-primary-900/30"
                    :class="{ 'bg-primary-50 dark:bg-primary-900/30': actif === filtered.length }"
                    @mousedown.prevent="creer"
                    @mousemove="actif = filtered.length"
                >
                    <slot name="create" :query="query.trim()">+ Créer « {{ query.trim() }} »</slot>
                </li>
            </ul>
        </Teleport>
    </div>
</template>
