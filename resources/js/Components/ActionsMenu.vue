<script setup>
import { MoreHorizontal } from '@lucide/vue';
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';

/**
 * Menu d'actions « ⋯ » accessible (motif ARIA « menu button ») :
 * - bouton libellé (`libelle`, ex. « Actions pour Jean Dupont »), aria-haspopup/aria-expanded ;
 * - à l'ouverture, le focus va sur la première action ; flèches haut/bas,
 *   Début/Fin pour naviguer ; Échap ou Tab ferment le menu, Échap rend le
 *   focus au bouton ; clic à l'extérieur ferme.
 * - le menu est téléporté dans <body> en position fixe : il n'est jamais
 *   rogné par un conteneur `overflow-hidden` et ne crée aucun défilement.
 *
 * actions : [{ cle, libelle, icone?, danger?, desactive?, aide? }] ; l'action
 * choisie est émise via `choisir` (cle).
 */
const props = defineProps({
    libelle: { type: String, required: true },
    actions: { type: Array, required: true },
});

const emit = defineEmits(['choisir']);

const id = useId();
const ouvert = ref(false);
const bouton = ref(null);
const menu = ref(null);
const position = ref({ top: 0, left: 0 });

const LARGEUR_MENU = 240;

const items = () => Array.from(menu.value?.querySelectorAll('[role="menuitem"]:not([disabled])') ?? []);

const positionner = () => {
    const rect = bouton.value?.getBoundingClientRect();
    if (!rect) return;
    const marge = 8;
    const largeurVue = document.documentElement.clientWidth;
    const hauteurVue = window.innerHeight;
    const hauteurMenu = menu.value?.offsetHeight ?? 0;

    let left = rect.right - LARGEUR_MENU;
    left = Math.max(marge, Math.min(left, largeurVue - LARGEUR_MENU - marge));

    let top = rect.bottom + 4;
    if (hauteurMenu && top + hauteurMenu > hauteurVue - marge && rect.top - hauteurMenu - 4 > marge) {
        top = rect.top - hauteurMenu - 4;
    }

    position.value = { top, left };
};

const surClicExterieur = (e) => {
    if (!menu.value?.contains(e.target) && !bouton.value?.contains(e.target)) {
        fermer(false);
    }
};

const ecouter = (actif) => {
    const methode = actif ? 'addEventListener' : 'removeEventListener';
    document[methode]('pointerdown', surClicExterieur, true);
    window[methode]('resize', positionner);
    window[methode]('scroll', positionner, true);
};

const ouvrir = async (focusIndex = 0) => {
    ouvert.value = true;
    ecouter(true);
    await nextTick();
    positionner();
    const liste = items();
    liste[focusIndex === -1 ? liste.length - 1 : focusIndex]?.focus();
};

const fermer = (rendreFocus = true) => {
    if (!ouvert.value) return;
    ouvert.value = false;
    ecouter(false);
    if (rendreFocus) bouton.value?.focus();
};

const basculer = () => (ouvert.value ? fermer() : ouvrir());

const surToucheBouton = (e) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        ouvrir(0);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        ouvrir(-1);
    }
};

const surToucheMenu = (e) => {
    const liste = items();
    const index = liste.indexOf(document.activeElement);
    const cibles = {
        ArrowDown: (index + 1) % liste.length,
        ArrowUp: (index - 1 + liste.length) % liste.length,
        Home: 0,
        End: liste.length - 1,
    };

    if (e.key in cibles) {
        e.preventDefault();
        liste[cibles[e.key]]?.focus();
    } else if (e.key === 'Escape') {
        e.preventDefault();
        e.stopPropagation();
        fermer();
    } else if (e.key === 'Tab') {
        fermer(false);
    }
};

const choisir = (action) => {
    if (action.desactive) return;
    fermer();
    emit('choisir', action.cle);
};

onBeforeUnmount(() => ecouter(false));
</script>

<template>
    <div class="inline-flex">
        <button
            ref="bouton"
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-lg text-neutral-600 ring-1 ring-neutral-200 transition hover:bg-neutral-100 hover:text-neutral-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-neutral-300 dark:ring-neutral-600 dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
            :aria-label="libelle"
            :title="libelle"
            aria-haspopup="menu"
            :aria-expanded="ouvert ? 'true' : 'false'"
            :aria-controls="ouvert ? `${id}-menu` : undefined"
            @click="basculer"
            @keydown="surToucheBouton"
        >
            <MoreHorizontal class="h-5 w-5" aria-hidden="true" />
        </button>

        <Teleport to="body">
            <div
                v-if="ouvert"
                :id="`${id}-menu`"
                ref="menu"
                role="menu"
                :aria-label="libelle"
                class="fixed z-[60] max-w-[calc(100vw-1rem)] rounded-lg bg-white py-1 shadow-lg ring-1 ring-neutral-200 dark:bg-neutral-800 dark:ring-neutral-700"
                :style="{ top: `${position.top}px`, left: `${position.left}px`, width: `${LARGEUR_MENU}px` }"
                @keydown="surToucheMenu"
            >
                <button
                    v-for="action in actions"
                    :key="action.cle"
                    type="button"
                    role="menuitem"
                    tabindex="-1"
                    :disabled="action.desactive || undefined"
                    :aria-disabled="action.desactive ? 'true' : undefined"
                    :title="action.aide || undefined"
                    class="flex min-h-[44px] w-full items-center gap-2 px-4 py-2 text-left text-sm focus:outline-none focus-visible:bg-neutral-100 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-light disabled:cursor-not-allowed disabled:opacity-50 dark:focus-visible:bg-neutral-700"
                    :class="action.danger
                        ? 'text-danger hover:bg-danger-50 dark:text-danger-300 dark:hover:bg-danger-900/30'
                        : 'text-neutral-800 hover:bg-neutral-100 dark:text-neutral-100 dark:hover:bg-neutral-700'"
                    @click="choisir(action)"
                >
                    <component :is="action.icone" v-if="action.icone" class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 break-words">{{ action.libelle }}</span>
                </button>
            </div>
        </Teleport>
    </div>
</template>
