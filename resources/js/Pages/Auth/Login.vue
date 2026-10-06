<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const emailInput = ref(null);

// Sur mobile, le clavier à l'écran masquait le champ mot de passe : on fait
// remonter le champ actif au centre de la zone visible, et on ne donne le
// focus automatique qu'aux grands écrans (sinon le clavier s'ouvre dès l'arrivée).
let minuteur = null;

const centrerChamp = (event) => {
    const champ = event.target;
    // Un seul défilement en attente : si le focus passe vite d'un champ à l'autre,
    // seul le dernier champ actif est recentré.
    clearTimeout(minuteur);
    minuteur = setTimeout(() => champ.scrollIntoView({ block: 'center', behavior: 'smooth' }), 300);
};

onBeforeUnmount(() => clearTimeout(minuteur));

onMounted(() => {
    if (window.matchMedia('(min-width: 640px)').matches) {
        emailInput.value?.focus();
    }
});
</script>

<template>
    <GuestLayout>
        <Head title="Connexion" />

        <div v-if="status" class="mb-4 text-sm font-medium text-success-700 dark:text-success-400">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="Email" />

                <TextInput
                    id="email"
                    ref="emailInput"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    @focus="centrerChamp"
                />

                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="Mot de passe" />

                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    @focus="centrerChamp"
                />

                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="rounded-md text-sm font-medium text-primary-light underline hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary-light focus:ring-offset-2"
                >
                    Mot de passe oublié ?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Se connecter
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
