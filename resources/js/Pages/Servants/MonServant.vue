<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    servant: Object,
    etapes: Array,
});
</script>

<template>
    <Head :title="`${servant.prenom} ${servant.nom}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    {{ servant.prenom }} {{ servant.nom }}
                </h2>
                <Link :href="route('dashboard')" class="text-sm text-neutral-600 hover:text-neutral-900">← Retour</Link>
            </div>
        </template>

        <div class="mx-auto max-w-3xl space-y-6">
            <p class="text-sm text-neutral-600">
                Consultation en lecture seule du parcours d'intégration — ce servant est affecté à un shift que vous gérez.
            </p>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-neutral-600">Téléphone</p>
                        <p class="text-neutral-900">{{ servant.telephone ?? '—' }}</p>
                    </div>
                    <div v-if="servant.titre_leadership" class="text-right">
                        <p class="text-sm text-neutral-600">Titre de leadership</p>
                        <p class="text-neutral-900">{{ servant.titre_leadership }}</p>
                    </div>
                    <StatusBadge :statut="servant.statut" />
                </div>
            </div>

            <div class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100">
                <h3 class="mb-4 text-base font-semibold text-neutral-900">Parcours d'intégration</h3>
                <div class="space-y-3">
                    <div v-for="etape in etapes" :key="etape.id" class="rounded-md border border-neutral-100 p-4">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-neutral-900">{{ etape.ordre }}. {{ etape.nom }}</div>
                            <StatusBadge :statut="etape.statut" />
                        </div>
                        <div class="mt-2 text-sm text-neutral-600">
                            <span v-if="etape.date">Le {{ etape.date }}</span>
                            <span v-if="etape.responsable"> — par {{ etape.responsable }}</span>
                            <p v-if="etape.commentaire" class="mt-1">{{ etape.commentaire }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
