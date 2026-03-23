<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

interface Props {
    title: string;
    intro?: string;
    eyebrow?: string;
    backHref?: string;
    backLabel?: string;
    maxWidth?: string;
}

withDefaults(defineProps<Props>(), {
    intro: '',
    eyebrow: '',
    backHref: '',
    backLabel: '',
    maxWidth: 'max-w-lg',
});
</script>

<template>
    <div class="luminous-shell">
        <div class="relative z-10 flex min-h-screen flex-col">
            <header class="border-b border-white/6 bg-black/20 backdrop-blur-2xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link :href="route('home')" class="shrink-0">
                        <BrandMark />
                    </Link>

                    <Link
                        v-if="backHref"
                        :href="backHref"
                        class="inline-flex min-h-11 items-center gap-2 rounded-full border border-white/8 bg-white/[0.045] px-4 text-sm font-medium text-white/75 hover:bg-white/[0.08] hover:text-white"
                    >
                        <ArrowLeft class="h-4 w-4" />
                        {{ backLabel || 'Back' }}
                    </Link>
                </div>
            </header>

            <main class="flex flex-1 items-center px-4 py-10 sm:px-6">
                <div class="mx-auto w-full" :class="maxWidth">
                    <div class="mb-8 text-center">
                        <p v-if="eyebrow" class="mb-3 text-[0.72rem] font-semibold tracking-[0.24em] text-primary/75 uppercase">
                            {{ eyebrow }}
                        </p>
                        <h1 class="text-gradient-filament font-display text-3xl font-semibold tracking-tight sm:text-4xl">
                            {{ title }}
                        </h1>
                        <p v-if="intro" class="text-muted-soft mx-auto mt-3 max-w-md text-sm leading-6 sm:text-base">
                            {{ intro }}
                        </p>
                    </div>

                    <div class="luminous-panel px-6 py-7 sm:px-8 sm:py-9">
                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
