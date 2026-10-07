<script>
// Pile des fenêtres ouvertes : Échap ne ferme que la plus récente (ex. une
// confirmation ouverte par-dessus une fenêtre de modification).
const pile = [];
let compteur = 0;
</script>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    maxWidth: {
        type: String,
        default: '2xl',
    },
    closeable: {
        type: Boolean,
        default: true,
    },
    // id du titre de la fenêtre (nom accessible du dialogue).
    labelledby: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(['close']);
const dialog = ref();
const showSlot = ref(props.show);
const identifiant = ++compteur;
// Élément qui avait le focus à l'ouverture : il le retrouve à la fermeture.
let focusPrecedent = null;

const retirerDeLaPile = () => {
    const i = pile.indexOf(identifiant);
    if (i !== -1) pile.splice(i, 1);
};

watch(
    () => props.show,
    () => {
        if (props.show) {
            focusPrecedent = document.activeElement;
            document.body.style.overflow = 'hidden';
            showSlot.value = true;
            pile.push(identifiant);

            // showModal() rend le reste de la page inerte : le focus reste piégé dans la fenêtre.
            dialog.value?.showModal();
            nextTick(() => {
                const cible = dialog.value?.querySelector('[autofocus], input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])');
                cible?.focus();
            });
        } else {
            retirerDeLaPile();
            if (pile.length === 0) {
                document.body.style.overflow = '';
            }

            setTimeout(() => {
                dialog.value?.close();
                showSlot.value = false;
                if (focusPrecedent && document.contains(focusPrecedent)) {
                    focusPrecedent.focus();
                }
                focusPrecedent = null;
            }, 200);
        }
    },
);

const close = () => {
    if (props.closeable) {
        emit('close');
    }
};

const closeOnEscape = (e) => {
    if (e.key === 'Escape') {
        if (props.show && pile[pile.length - 1] === identifiant) {
            e.preventDefault();
            close();
        }
    }
};

onMounted(() => document.addEventListener('keydown', closeOnEscape));

onUnmounted(() => {
    document.removeEventListener('keydown', closeOnEscape);
    retirerDeLaPile();

    document.body.style.overflow = '';
});

const maxWidthClass = computed(() => {
    return {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[props.maxWidth];
});
</script>

<template>
    <dialog
        :aria-labelledby="labelledby || undefined"
        class="z-50 m-0 min-h-full min-w-full overflow-y-auto border-none bg-transparent p-0 backdrop:bg-transparent"
        ref="dialog"
    >
        <div
            class="fixed inset-0 z-50 overflow-y-auto px-4 py-6 sm:px-0"
            scroll-region
        >
            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-show="show"
                    class="fixed inset-0 z-0 transform transition-all"
                    @click="close"
                >
                    <div
                        class="absolute inset-0 bg-neutral-500 opacity-75 dark:bg-neutral-950 dark:opacity-80"
                    />
                </div>
            </Transition>

            <Transition
                enter-active-class="ease-out duration-300"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <div
                    v-show="show"
                    class="relative z-10 mb-6 transform overflow-hidden rounded-lg bg-white shadow-xl transition-all dark:bg-neutral-800 sm:mx-auto sm:w-full"
                    :class="maxWidthClass"
                >
                    <slot v-if="showSlot" />
                </div>
            </Transition>
        </div>
    </dialog>
</template>
