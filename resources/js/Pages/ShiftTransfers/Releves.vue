<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Repeat, UserRound } from '@lucide/vue';

defineProps({
    releves: Object,
});
</script>

<template>
    <Head title="Servants relevés" />

    <AuthenticatedLayout :breadcrumbs="[{ label: 'Tableau de bord', href: route('dashboard') }, { label: 'Changement', href: route('shift-transfers.index') }, { label: 'Servants relevés' }]">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900">
                    <Repeat class="h-5 w-5 text-primary" />
                    Servants relevés
                </h2>
                <Link :href="route('shift-transfers.index')" class="text-sm font-medium text-primary-light hover:text-primary">
                    ← Retour aux transferts
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <p class="text-sm text-neutral-600">
                Historique des servants relevés de leur poste suite à une demande de relève traitée. Leur poste est redevenu vacant.
            </p>

            <div v-if="releves.data.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Aucun servant relevé pour l'instant.
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="r in releves.data"
                    :key="r.id"
                    class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100"
                >
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 font-medium text-neutral-900">
                                <UserRound class="h-4 w-4 text-primary" />
                                {{ r.servant }}
                            </div>
                            <div class="mt-1 text-sm text-neutral-600">
                                Relevé du shift <span class="font-medium text-neutral-700">{{ r.shift }}</span>
                                <span v-if="r.coordonnees">· {{ r.coordonnees }}</span>
                            </div>
                            <p class="mt-2 text-sm text-neutral-600">{{ r.motif }}</p>
                        </div>
                        <div class="text-right text-sm text-neutral-600">
                            <p>{{ r.resultat_date }}</p>
                            <p v-if="r.decideur" class="text-xs text-neutral-500">par {{ r.decideur }}</p>
                        </div>
                    </div>
                    <p v-if="r.resultat" class="mt-3 border-t border-neutral-100 pt-3 text-sm text-neutral-600">
                        {{ r.resultat }}
                    </p>
                </div>
            </div>

            <div v-if="releves.links?.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="link in releves.links" :key="link.label">
                    <span
                        v-if="!link.url"
                        class="rounded-md px-3 py-1.5 text-sm text-neutral-400"
                        v-html="link.label"
                    />
                    <Link
                        v-else
                        :href="link.url"
                        preserve-scroll
                        preserve-state
                        class="rounded-md px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-white' : 'bg-white text-neutral-600 ring-1 ring-neutral-200 hover:bg-neutral-50'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
