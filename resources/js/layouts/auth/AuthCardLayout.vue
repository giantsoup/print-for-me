<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="luminous-shell">
        <div class="relative z-10 flex min-h-screen flex-col justify-center px-6 py-10">
            <div class="mx-auto flex w-full max-w-lg flex-col gap-6">
                <div class="flex justify-center">
                    <Link :href="route('home')">
                        <BrandMark />
                    </Link>
                </div>

                <div class="luminous-panel px-6 py-8 sm:px-8">
                    <div v-if="title || description" class="mb-8 text-center">
                        <h1 v-if="title" class="text-gradient-filament font-display text-3xl font-semibold tracking-tight">
                            {{ title }}
                        </h1>
                        <p v-if="description" class="text-muted-soft mt-3 text-sm leading-6">
                            {{ description }}
                        </p>
                    </div>

                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
