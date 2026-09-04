import { ref, watchEffect } from 'vue';

const STOCKAGE_CLE = 'theme';
const media = window.matchMedia('(prefers-color-scheme: dark)');

// Initialisé depuis localStorage : le script inline dans app.blade.php a déjà
// posé la classe `dark` sur <html> avant le montage de Vue (anti-flash), ce
// module ne fait que reprendre la main sur cet état pour le rendre réactif.
const theme = ref(localStorage.getItem(STOCKAGE_CLE) ?? 'system');

const appliquer = () => {
    const sombre = theme.value === 'dark' || (theme.value === 'system' && media.matches);
    document.documentElement.classList.toggle('dark', sombre);
};

watchEffect(appliquer);
media.addEventListener('change', () => {
    if (theme.value === 'system') appliquer();
});

/**
 * État de thème partagé (light/dark/system) synchronisé avec localStorage et
 * la classe `dark` sur <html>. Un seul état de module : tous les appels de
 * useTheme() à travers l'app partagent la même préférence.
 */
export function useTheme() {
    const definir = (valeur) => {
        theme.value = valeur;
        localStorage.setItem(STOCKAGE_CLE, valeur);
    };

    return { theme, definirTheme: definir };
}
