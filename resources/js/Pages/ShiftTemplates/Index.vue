<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    templates: Array,
});
</script>

<template>
    <Head title="Modèles de Shift" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-neutral-900">
                    Modèles de Shift
                </h2>
                <Link :href="route('shift-templates.create')">
                    <PrimaryButton>+ Créer un modèle</PrimaryButton>
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-6">
            <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-neutral-100">
                        <thead class="bg-neutral-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Nom</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">Postes</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100 bg-white">
                            <tr v-if="templates.length === 0">
                                <td colspan="3" class="px-6 py-8 text-center text-neutral-600">
                                    Aucun modèle pour le moment.
                                </td>
                            </tr>
                            <tr v-for="template in templates" :key="template.id">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-neutral-900">
                                    {{ template.nom }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm text-neutral-600">
                                    {{ template.positions_count }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                    <Link :href="route('shift-templates.show', template.id)" class="font-medium text-primary-light hover:text-primary">
                                        Voir
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
