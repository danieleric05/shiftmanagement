<script setup>
import { Link } from '@inertiajs/vue3';
import { UsersRound } from '@lucide/vue';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center bg-neutral-50 pt-6 sm:justify-center sm:pt-0">
        <div class="flex flex-col items-center">
            <Link href="/" class="flex h-14 w-14 items-center justify-center rounded-xl bg-primary">
                <UsersRound class="h-7 w-7 text-white" stroke-width="2.25" />
            </Link>
            <span class="mt-3 text-lg font-semibold text-neutral-900">Shift Management</span>
            <span class="text-xs text-neutral-600">Plateforme de gestion des Shifts</span>
        </div>

        <div class="mt-6 w-full rounded-xl bg-white px-6 py-4 shadow-card ring-1 ring-neutral-100 sm:max-w-md">
            <slot />
        </div>
    </div>
</template>
