<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import StatCard from '@/Components/StatCard.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { UserCheck, UserPlus } from '@lucide/vue';

const props = defineProps({
    shifts: Array,
    estAdministrateur: Boolean,
    compteurs: Object,
});

const forms = reactive(
    Object.fromEntries(
        props.shifts.map((s) => [
            s.shift_id,
            useForm({
                nombre_a_recruter: s.nombre_a_recruter,
                echeance: s.echeance ?? '',
                notes: s.notes ?? '',
            }),
        ]),
    ),
);

const enregistrer = (shiftId) => {
    forms[shiftId].put(route('recruitment.upsert', shiftId), {
        preserveScroll: true,
    });
};

const progression = (shift) => {
    const cible = forms[shift.shift_id].nombre_a_recruter;
    if (!cible || cible <= 0) return null;
    return Math.min(100, Math.round((shift.candidats_actifs / cible) * 100));
};
</script>

<template>
    <Head title="Recrutement" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="flex items-center gap-2 text-xl font-semibold leading-tight text-neutral-900">
                <UserPlus class="h-5 w-5 text-primary" />
                Besoins de recrutement
            </h2>
        </template>

        <div class="mx-auto max-w-6xl space-y-6">
            <div class="grid grid-cols-2 gap-4">
                <StatCard label="Total à recruter" :value="compteurs.total_a_recruter" :icon="UserPlus" tone="primary" />
                <StatCard label="Candidats actifs" :value="compteurs.total_candidats_actifs" :icon="UserCheck" tone="success" />
            </div>

            <div v-if="shifts.length === 0" class="rounded-xl bg-white p-8 text-center text-neutral-600 shadow-card ring-1 ring-neutral-100">
                Vous ne gérez aucun Shift pour l'instant — les besoins de recrutement apparaîtront ici dès qu'un Shift vous sera confié.
            </div>

            <div
                v-for="shift in shifts"
                :key="shift.shift_id"
                class="rounded-xl bg-white p-6 shadow-card ring-1 ring-neutral-100 transition hover:shadow-md"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="font-medium text-neutral-900">{{ shift.shift_nom }}</h3>
                        <p class="text-sm text-neutral-600">{{ shift.candidats_actifs }} candidat(s) actif(s) sur ce Shift</p>
                    </div>
                    <div v-if="progression(shift) !== null" class="w-40 shrink-0">
                        <div class="flex items-center justify-between text-xs text-neutral-500">
                            <span>Pourvu</span>
                            <span class="font-medium" :class="progression(shift) >= 100 ? 'text-success-700' : 'text-neutral-700'">
                                {{ progression(shift) }}%
                            </span>
                        </div>
                        <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-neutral-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="progression(shift) >= 100 ? 'bg-success-600' : 'bg-primary-light'"
                                :style="{ width: `${progression(shift)}%` }"
                            />
                        </div>
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Nombre à recruter</label>
                        <input
                            type="number"
                            min="0"
                            v-model.number="forms[shift.shift_id].nombre_a_recruter"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        />
                        <InputError class="mt-1" :message="forms[shift.shift_id].errors.nombre_a_recruter" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Échéance</label>
                        <input
                            type="date"
                            v-model="forms[shift.shift_id].echeance"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        />
                        <InputError class="mt-1" :message="forms[shift.shift_id].errors.echeance" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-neutral-700">Notes</label>
                        <input
                            type="text"
                            v-model="forms[shift.shift_id].notes"
                            class="mt-1 block w-full rounded-md border-neutral-300 text-sm shadow-sm focus:border-primary-light focus:ring-primary-light"
                        />
                        <InputError class="mt-1" :message="forms[shift.shift_id].errors.notes" />
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <PrimaryButton :disabled="forms[shift.shift_id].processing" @click="enregistrer(shift.shift_id)">
                        Enregistrer
                    </PrimaryButton>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
