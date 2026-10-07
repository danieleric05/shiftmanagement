<script setup>
import Badge from '@/Components/Badge.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Liste compacte des shifts du tableau de bord (Frères / Sœurs), dans l'ordre
 * du calendrier fourni par le serveur. Les noms longs sont tronqués avec
 * infobulle ; chaque lien est une cible d'au moins 44 px.
 *
 * lectureSeuleSiNonGere : affiche « Lecture seule » pour les shifts que le
 * coordonnateur ne gère pas (champ `gere`).
 */
const props = defineProps({
    shifts: { type: Array, required: true },
    routeFiche: { type: String, required: true },
    lectureSeuleSiNonGere: { type: Boolean, default: false },
});

const groupes = computed(() => [
    { cle: 'freres', titre: 'Frères', shifts: props.shifts.filter((s) => s.genre === 'freres') },
    { cle: 'soeurs', titre: 'Sœurs', shifts: props.shifts.filter((s) => s.genre === 'soeurs') },
]);

const libelle = (s) => `${s.jour.charAt(0).toUpperCase()}${s.jour.slice(1)} — ${s.nom}`;
</script>

<template>
    <div v-if="shifts.length === 0" class="text-sm text-neutral-600 dark:text-neutral-400">
        Aucun Shift créé pour le moment.
    </div>
    <div v-else class="grid grid-cols-1 gap-x-8 gap-y-6 sm:grid-cols-2">
        <div v-for="g in groupes" :key="g.cle" class="min-w-0">
            <h4 :id="`shifts-${g.cle}`" class="mb-2 text-xs font-semibold uppercase tracking-wide text-neutral-600 dark:text-neutral-400">{{ g.titre }}</h4>
            <ul v-if="g.shifts.length" role="list" :aria-labelledby="`shifts-${g.cle}`" class="space-y-1">
                <li v-for="shift in g.shifts" :key="shift.id">
                    <Link
                        :href="route(routeFiche, shift.id)"
                        class="flex min-h-[44px] min-w-0 items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm hover:bg-neutral-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:hover:bg-neutral-700"
                    >
                        <span class="min-w-0 truncate text-neutral-900 dark:text-neutral-100" :title="libelle(shift)">{{ libelle(shift) }}</span>
                        <span class="flex shrink-0 items-center gap-2">
                            <Badge v-if="lectureSeuleSiNonGere && !shift.gere" variant="neutral">Lecture seule</Badge>
                            <Badge v-else-if="shift.postes_total === 0" variant="neutral">—</Badge>
                            <Badge v-else-if="shift.postes_vacants === 0" variant="success">Voir</Badge>
                            <Badge v-else variant="warning">{{ shift.postes_vacants }} vacant(s)</Badge>
                        </span>
                    </Link>
                </li>
            </ul>
            <p v-else class="px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400">Aucun Shift.</p>
        </div>
    </div>
</template>
