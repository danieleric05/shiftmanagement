<script setup>
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Head, Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';

defineProps({
    title: String,
});
</script>

<template>
    <Head :title="title" />

    <div class="min-h-screen bg-neutral-50 pb-16 dark:bg-neutral-900">
        <header class="border-b border-neutral-100 bg-white dark:border-neutral-700 dark:bg-neutral-800">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-6 py-5">
                <div class="flex items-center gap-3">
                    <Link href="/" class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary">
                        <UsersRound class="h-5 w-5 text-white" stroke-width="2.25" />
                    </Link>
                    <span class="font-semibold text-neutral-900 dark:text-neutral-100">Shift Management</span>
                </div>
                <ThemeToggle />
            </div>
        </header>

        <div class="mx-auto max-w-3xl px-6 py-10">
            <div class="rounded-xl bg-white p-8 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700">
                <h1 class="text-2xl font-semibold text-neutral-900 dark:text-neutral-100">{{ title }}</h1>
                <div class="legal-content mt-6 space-y-4 text-sm leading-relaxed text-neutral-700 dark:text-neutral-300">
                    <slot />
                </div>
            </div>

            <div class="mt-6 flex justify-center gap-4 text-sm text-neutral-500">
                <Link href="/cgu" class="hover:text-neutral-700 dark:hover:text-neutral-300">Conditions générales d'utilisation</Link>
                <span>·</span>
                <Link href="/confidentialite" class="hover:text-neutral-700 dark:hover:text-neutral-300">Politique de confidentialité</Link>
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
:root.dark .legal-content :deep(h2) {
    color: theme('colors.neutral.100');
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
:root.dark .legal-content :deep(strong) {
    color: theme('colors.neutral.100');
}
</style>
