<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isAuthed = computed(() => Boolean(page.props.auth.user));
</script>

<template>
    <div class="luminous-shell">
        <div class="relative z-10 min-h-screen">
            <header class="border-b border-white/6 bg-black/20 backdrop-blur-2xl">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                    <Link :href="route('home')" class="shrink-0">
                        <BrandMark />
                    </Link>

                    <nav class="hidden items-center gap-2 md:flex">
                        <a href="#how-it-works" class="rounded-full px-4 py-2 text-sm font-medium text-white/60 hover:bg-white/[0.045] hover:text-white">
                            How it works
                        </a>
                        <a href="#why-it-works" class="rounded-full px-4 py-2 text-sm font-medium text-white/60 hover:bg-white/[0.045] hover:text-white">
                            Why it works
                        </a>
                    </nav>

                    <div class="flex items-center gap-2">
                        <Link
                            :href="isAuthed ? route('dashboard') : route('login')"
                            class="pill-button pill-button-primary"
                        >
                            {{ isAuthed ? 'Open dashboard' : 'Request login link' }}
                        </Link>
                    </div>
                </div>
            </header>

            <main class="relative z-10">
                <slot />
            </main>
        </div>
    </div>
</template>
