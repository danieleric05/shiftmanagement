<script setup>
import ThemeToggle from '@/Components/ThemeToggle.vue';
import { Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center bg-neutral-50 pt-6 dark:bg-neutral-900 sm:justify-center sm:pt-0">
        <ThemeToggle class="absolute right-4 top-4" />

        <div class="flex flex-col items-center">
            <Link href="/" class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary">
                <UsersRound class="h-7 w-7 text-white" stroke-width="2.25" />
            </Link>
            <span class="mt-3 text-lg font-semibold text-neutral-900 dark:text-neutral-100">Shift Management</span>
            <span class="text-xs text-neutral-600 dark:text-neutral-400">Plateforme de gestion des Shifts</span>
        </div>

        <div class="mt-6 w-full rounded-xl bg-white px-6 py-4 shadow-card ring-1 ring-neutral-100 dark:bg-neutral-800 dark:ring-neutral-700 sm:max-w-md">
            <slot />
        </div>

        <div class="mt-6 flex gap-4 text-xs text-neutral-500 dark:text-neutral-500">
            <Link href="/cgu" class="hover:text-neutral-700 dark:hover:text-neutral-300">CGU</Link>
            <span>·</span>
            <Link href="/confidentialite" class="hover:text-neutral-700 dark:hover:text-neutral-300">Confidentialité</Link>
        </div>
    </div>
</template>
