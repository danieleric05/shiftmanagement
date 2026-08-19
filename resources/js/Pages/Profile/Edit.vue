<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Badge from '@/Components/Badge.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { useRoleTheme } from '@/composables/useRoleTheme';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Mail, Phone } from '@lucide/vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const avertissement = computed(() => page.props.flash?.warning);
const { theme, initials } = useRoleTheme();
</script>

<template>
    <Head title="Mon profil" />

    <AuthenticatedLayout>
        <template #header>Mon profil</template>

        <div class="mx-auto max-w-3xl space-y-6">
            <div v-if="avertissement" class="rounded-xl bg-warning/10 p-4 text-sm font-medium text-amber-800 ring-1 ring-warning/30">
                {{ avertissement }}
            </div>

            <!-- Carte d'identité -->
            <div class="overflow-hidden rounded-xl bg-white shadow-card ring-1 ring-neutral-100">
                <div class="h-16" :class="theme.aside" />
                <div class="-mt-8 px-6 pb-6">
                    <div class="flex items-end gap-4">
                        <span
                            class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-xl font-semibold text-white ring-4 ring-white"
                            :class="theme.aside"
                        >
                            {{ initials }}
                        </span>
                        <div class="pb-1">
                            <p class="text-lg font-semibold text-neutral-900">{{ user.name }}</p>
                            <Badge variant="info">{{ theme.roleLabel }}</Badge>
                        </div>
                    </div>
                    <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="flex items-center gap-2 text-sm text-neutral-600">
                            <Mail class="h-4 w-4 shrink-0 text-neutral-400" />
                            {{ user.email }}
                        </div>
                        <div v-if="user.telephone" class="flex items-center gap-2 text-sm text-neutral-600">
                            <Phone class="h-4 w-4 shrink-0 text-neutral-400" />
                            {{ user.telephone }}
                        </div>
                    </dl>
                </div>
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

            <div class="rounded-xl bg-danger-50/40 p-4 shadow-card ring-1 ring-danger/20 sm:p-8">
                <DeleteUserForm class="max-w-xl" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
