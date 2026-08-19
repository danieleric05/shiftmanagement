<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const avertissement = computed(() => page.props.flash?.warning);
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>Profile</template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div v-if="avertissement" class="rounded-xl bg-warning/10 p-4 text-sm font-medium text-amber-800 ring-1 ring-warning/30">
                {{ avertissement }}
            </div>

            <div class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 sm:p-8">
                <UpdateProfileInformationForm
                    :must-verify-email="mustVerifyEmail"
                    :status="status"
                    class="max-w-xl"
                />
            </div>

            <div class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 sm:p-8">
                <UpdatePasswordForm class="max-w-xl" />
            </div>

            <div class="rounded-xl bg-white p-4 shadow-card ring-1 ring-neutral-100 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
