<script setup>
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useConfirm } from '@/composables/useConfirm';

const { state, repondre } = useConfirm();
</script>

<template>
    <Modal :show="state.show" max-width="md" @close="repondre(false)">
        <div class="p-6">
            <h2 class="text-lg font-semibold text-neutral-900">{{ state.title }}</h2>
            <p class="mt-2 text-sm text-neutral-600">{{ state.message }}</p>

            <div class="mt-6 flex justify-end gap-3">
                <SecondaryButton @click="repondre(false)">Annuler</SecondaryButton>
                <DangerButton v-if="state.danger" @click="repondre(true)">Confirmer</DangerButton>
                <PrimaryButton v-else @click="repondre(true)">Confirmer</PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
