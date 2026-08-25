<script setup>
import { ChevronDown, ChevronsUpDown, ChevronUp } from '@lucide/vue';

const props = defineProps({
    label: { type: String, required: true },
    sortKey: { type: String, required: true },
    activeKey: { type: String, default: null },
    direction: { type: String, default: 'asc' },
});

const emit = defineEmits(['sort']);
</script>

<template>
    <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-neutral-600">
        <button
            type="button"
            class="inline-flex items-center gap-1 hover:text-neutral-900"
            @click="emit('sort', sortKey)"
        >
            {{ label }}
            <ChevronUp v-if="activeKey === sortKey && direction === 'asc'" class="h-3 w-3" />
            <ChevronDown v-else-if="activeKey === sortKey && direction === 'desc'" class="h-3 w-3" />
            <ChevronsUpDown v-else class="h-3 w-3 text-neutral-400" />
        </button>
    </th>
</template>
