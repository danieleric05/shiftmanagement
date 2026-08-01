<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';

defineProps({
    title: String,
});
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-neutral-50 pb-16">
        <header class="border-b border-neutral-100 bg-white">
            <div class="mx-auto flex max-w-3xl items-center gap-3 px-6 py-5">
                <Link href="/" class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
                    <UsersRound class="h-5 w-5 text-white" stroke-width="2.25" />
                </Link>
                <span class="font-semibold text-neutral-900">Shift Management</span>
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <div class="rounded-xl bg-white p-8 shadow-card ring-1 ring-neutral-100">
                <h1 class="text-2xl font-semibold text-neutral-900">{{ title }}</h1>
                <div class="legal-content mt-6 space-y-4 text-sm leading-relaxed text-neutral-700">
                    <slot />
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-4 text-sm text-neutral-500">
                <Link href="/cgu" class="hover:text-neutral-700">Conditions générales d'utilisation</Link>
                <span>·</span>
                <Link href="/confidentialite" class="hover:text-neutral-700">Politique de confidentialité</Link>
            </div>
        </div>
    </div>
</template>

<style scoped>
.legal-content :deep(h2) {
    margin-top: 1.5rem;
    font-size: 1.05rem;
    font-weight: 600;
    color: theme('colors.neutral.900');
}
.legal-content :deep(ul) {
    list-style: disc;
    padding-left: 1.25rem;
}
.legal-content :deep(li) {
    margin-top: 0.25rem;
}
.legal-content :deep(strong) {
    color: theme('colors.neutral.900');
}
</style>
