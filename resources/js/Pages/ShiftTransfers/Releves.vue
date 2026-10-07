<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { libellePagination } from '@/composables/usePagination';
import ReintegrationDialog from '@/Components/ReintegrationDialog.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Repeat, UserRound } from '@lucide/vue';
import { ref } from 'vue';

defineProps({
    releves: Object,
    shiftsReintegration: { type: Array, default: () => [] },
});

// Servant ciblé par la confirmation de réintégration (Conseil du Temple).
const servantAReintegrer = ref(null);
const ouvrirReintegration = (r) => {
    servantAReintegrer.value = { id: r.servant_id, nom: r.servant, genre: r.genre };
};
</script>

<template>
    <Head title="Servant(e)s relevé(e)s" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Changement', href: route('shift-transfers.index') }, { label: 'Servant(e)s relevé(e)s' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900 dark:text-neutral-100">
                    <Repeat class="h-5 w-5 text-primary" />
                    Servant(e)s relevé(e)s
                </h2>
                <Link :href="route('shift-transfers.index')" class="text-sm font-medium text-primary-light hover:text-primary">
                    ← Retour aux transferts
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <p class="text-sm text-neutral-600 dark:text-neutral-400">
                Historique des servant(e)s relevé(e)s de leur poste suite à une demande de relève traitée. Le Conseil peut réintégrer un servant(e) relevé(e) : la relève reste dans cet historique.
            </p>

            <div v-if="releves.data.length === 0" class="rounded-xl bg-white dark:bg-neutral-800 p-8 text-center text-neutral-600 dark:text-neutral-400 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700">
                Aucun servant(e) relevé(e) pour l'instant.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="r in releves.data"
                    :key="r.id"
                    class="rounded-xl bg-white dark:bg-neutral-800 p-6 shadow-card ring-1 ring-neutral-100 dark:ring-neutral-700"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 font-medium text-neutral-900 dark:text-neutral-100">
                                <UserRound class="h-4 w-4 text-primary" />
                                {{ r.servant }}
                            </div>
                            <div class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                                Relevé du shift <span class="font-medium text-neutral-700 dark:text-neutral-200">{{ r.shift }}</span>
                                <span v-if="r.coordonnees">· {{ r.coordonnees }}</span>
                            </div>
                            <p class="mt-2 text-sm text-neutral-600 dark:text-neutral-400">{{ r.motif }}</p>
                        </div>
                        <div class="text-right text-sm text-neutral-600 dark:text-neutral-400">
                            <p>{{ r.resultat_date }}</p>
                            <p v-if="r.decideur" class="text-xs text-neutral-500 dark:text-neutral-400">par {{ r.decideur }}</p>
                            <button
                                v-if="r.peut_reintegrer"
                                type="button"
                                class="mt-2 rounded-md bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary/90"
                                @click="ouvrirReintegration(r)"
                            >
                                Réintégrer
                            </button>
                        </div>
                    </div>
                    <p v-if="r.reintegre_le" class="mt-3 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
                        Réintégré(e) le {{ r.reintegre_le }}<span v-if="r.reintegre_par"> par {{ r.reintegre_par }}</span><span v-if="r.reintegration_commentaire"> — {{ r.reintegration_commentaire }}</span>
                    </p>
                    <p v-if="r.resultat" class="mt-3 border-t border-neutral-100 dark:border-neutral-700 pt-3 text-sm text-neutral-600 dark:text-neutral-400">
                        {{ r.resultat }}
                    </p>
                </div>
            </div>

            <div v-if="releves.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in releves.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                        v-html="libellePagination(link.label)"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-white' : 'bg-white dark:bg-neutral-800 text-neutral-600 dark:text-neutral-400 ring-1 ring-neutral-200 dark:ring-neutral-700 hover:bg-neutral-50 dark:hover:bg-neutral-700'"
                        v-html="libellePagination(link.label)"
                    />
                </template>
            </div>
        </div>

        <ReintegrationDialog
            :show="servantAReintegrer !== null"
            :servant="servantAReintegrer"
            :shifts="shiftsReintegration"
            @close="servantAReintegrer = null"
        />
    </AuthenticatedLayout>
</template>
