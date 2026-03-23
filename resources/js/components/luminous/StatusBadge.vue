<script setup lang="ts">
import { statusLabel, statusTone, type PrintRequestStatus } from '@/lib/prints';
import { computed } from 'vue';

interface Props {
    status: PrintRequestStatus;
}

const props = defineProps<Props>();

const classes = computed(() => statusTone(props.status));
</script>

<template>
    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-[0.68rem] font-semibold tracking-[0.22em] uppercase" :class="classes">
        <span class="h-1.5 w-1.5 rounded-full bg-current" />
        {{ statusLabel(status) }}
    </span>
</template>
