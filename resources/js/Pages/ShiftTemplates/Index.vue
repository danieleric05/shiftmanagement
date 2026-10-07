<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { useTableSearch } from '@/composables/useTableSearch';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    templates: Array,
});

const { recherche, resultats: templatesCherches } = useTableSearch(() => props.templates, ['nom']);

// Tri client (liste complète) ; ordre par défaut : nom (ordre du serveur).
const colonnes = [
    { cle: 'nom', libelle: 'Nom', triable: true, principale: true, priorite: 1, largeurMin: 220 },
    { cle: 'positions_count', libelle: 'Postes', triable: true, priorite: 1, largeurMin: 112, largeur: '7rem', alignement: 'fin' },
    { cle: 'voir', libelle: 'Voir', priorite: 1, largeurMin: 104, largeur: '6.5rem', alignement: 'fin', libelleMasque: true, carte: false },
];

const compteur = computed(() => {
    const n = templatesCherches.value.length;
    return `${n} modèle${n > 1 ? 's' : ''}${recherche.value ? ` trouvé${n > 1 ? 's' : ''}` : ''}`;
});

const lienClasse = 'inline-flex min-h-[44px] items-center rounded text-primary hover:text-primary-light focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light dark:text-primary-300';
</script>

<template>
    <Head title="Modèles de Shift" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Modèles de Shift' }]">
        <template #header>
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <h2 class="min-w-0 truncate text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100" title="Modèles de Shift">
                    Modèles de Shift
                </h2>
                <Link
                    :href="route('shift-templates.create')"
                    class="inline-flex min-h-[44px] shrink-0 items-center justify-center gap-1.5 rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light focus-visible:ring-offset-2 dark:focus-visible:ring-offset-neutral-900"
                >
                    <span aria-hidden="true">+</span>
                    Créer un modèle
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <SearchInput v-if="templates.length > 0" v-model="recherche" placeholder="Rechercher un modèle…" label="Rechercher un modèle par nom" />

            <DataTable
                :colonnes="colonnes"
                :lignes="templatesCherches"
                legende="Modèles de Shift"
                :filtre-actif="Boolean(recherche)"
                :compteur="compteur"
                message-vide="Aucun modèle pour le moment."
                message-aucun-resultat="Aucun modèle ne correspond à ces critères."
            >
                <template #cellule-nom="{ ligne, mode }">
                    <Link v-if="mode === 'carte'" :href="route('shift-templates.show', ligne.id)" :class="lienClasse">{{ ligne.nom }}</Link>
                    <template v-else>{{ ligne.nom }}</template>
                </template>
                <template #cellule-voir="{ ligne }">
                    <Link
                        :href="route('shift-templates.show', ligne.id)"
                        class="-my-3 inline-flex min-h-[44px] min-w-[44px] items-center justify-end rounded font-medium text-primary-light hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-light"
                    >
                        Voir<span class="sr-only"> le modèle {{ ligne.nom }}</span>
                    </Link>
                </template>
            </DataTable>
        </div>
    </AuthenticatedLayout>
</template>
