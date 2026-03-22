<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const name = page.props.name;
const quote = page.props.quote;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="luminous-shell">
        <div class="relative z-10 grid min-h-screen lg:grid-cols-2">
            <aside class="hidden border-r border-white/6 px-10 py-10 lg:flex lg:flex-col">
                <Link :href="route('home')" class="self-start">
                    <BrandMark />
                </Link>

                <div class="mt-auto luminous-panel px-6 py-6">
                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-primary/75">{{ name }}</p>
                    <blockquote v-if="quote" class="mt-4 space-y-3">
                        <p class="font-display text-3xl font-semibold tracking-tight text-white">&ldquo;{{ quote.message }}&rdquo;</p>
                        <footer class="text-sm text-muted-soft">{{ quote.author }}</footer>
                    </blockquote>
                </div>
            </aside>

            <main class="flex items-center px-6 py-10">
                <div class="mx-auto w-full max-w-md">
                    <div class="mb-8 lg:hidden">
                        <Link :href="route('home')">
                            <BrandMark />
                        </Link>
                    </div>

                    <div class="luminous-panel px-6 py-8 sm:px-8">
                        <div v-if="title || description" class="mb-8 text-center">
                            <h1 v-if="title" class="font-display text-3xl font-semibold tracking-tight text-gradient-filament">
                                {{ title }}
                            </h1>
                            <p v-if="description" class="mt-3 text-sm leading-6 text-muted-soft">
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
