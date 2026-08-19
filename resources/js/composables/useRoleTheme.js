import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const roleLabels = {
    administrateur: 'Administrateur',
    super_admin: 'Administrateur',
    chef_equipe: 'Chef d’équipe',
    chef_adjoint: 'Chef d’équipe adjoint',
    coordinateur: 'Coordinateur',
    coordinateur_adjoint: 'Coordinateur adjoint',
    servant: 'Servant',
    membre: 'Membre',
};

/**
 * Couleur/rôle associés à l'utilisateur connecté, partagés entre la barre
 * latérale (AuthenticatedLayout) et toute page qui doit refléter la même
 * identité visuelle (ex. le profil).
 */
export function useRoleTheme() {
    const page = usePage();
    const role = computed(() => page.props.auth.role);
    const user = computed(() => page.props.auth.user);

    const isAdmin = computed(() => ['administrateur', 'super_admin'].includes(role.value));
    const isGestionnaire = computed(() => ['chef_equipe', 'chef_adjoint', 'coordinateur', 'coordinateur_adjoint'].includes(role.value));

    const theme = computed(() => {
        if (isAdmin.value) {
            return {
                aside: 'bg-primary',
                brandSub: 'text-primary-100/80',
                linkActive: 'bg-white text-primary shadow-sm',
                linkInactive: 'text-primary-100/90 hover:bg-white/10 hover:text-white',
                roleLabel: roleLabels[role.value] ?? 'Administrateur',
            };
        }
        if (isGestionnaire.value) {
            return {
                aside: 'bg-success-700',
                brandSub: 'text-success-50/80',
                linkActive: 'bg-white text-success-700 shadow-sm',
                linkInactive: 'text-success-50/90 hover:bg-white/10 hover:text-white',
                roleLabel: roleLabels[role.value] ?? 'Chef d’équipe',
            };
        }
        return {
            aside: 'bg-membre',
            brandSub: 'text-membre-50/80',
            linkActive: 'bg-white text-membre shadow-sm',
            linkInactive: 'text-membre-50/90 hover:bg-white/10 hover:text-white',
            roleLabel: roleLabels[role.value] ?? 'Membre',
        };
    });

    const initials = computed(() => {
        const name = user.value?.name ?? '';
        return name
            .split(' ')
            .map((part) => part[0])
            .slice(0, 2)
            .join('')
            .toUpperCase();
    });

    return { role, isAdmin, isGestionnaire, theme, initials };
}
