<script setup lang="ts">
interface Props {
    compact?: boolean;
}

withDefaults(defineProps<Props>(), {
    compact: false,
});
</script>

<template>
    <div class="flex items-center gap-3">
        <div class="status-ring relative flex h-10 w-10 items-center justify-center rounded-2xl bg-white/[0.04]">
            <div class="absolute inset-2 rounded-xl bg-primary/12 blur-md" />
            <div class="relative grid gap-1">
                <span class="block h-2.5 w-5 -skew-x-12 rounded-sm bg-primary" />
                <span class="ml-1 block h-2.5 w-5 skew-x-12 rounded-sm bg-primary/80" />
                <span class="block h-2.5 w-5 -skew-x-12 rounded-sm bg-secondary/55" />
            </div>
        </div>

        <div v-if="!compact" class="min-w-0">
            <p class="text-gradient-filament font-display text-base font-semibold tracking-tight">
                {{ $page.props.name }}
            </p>
            <p class="text-[0.68rem] font-semibold tracking-[0.24em] text-white/40 uppercase">Private Print Queue</p>
        </div>
    </div>
</template>
