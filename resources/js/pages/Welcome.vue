<script setup lang="ts">
import LuminousPublicLayout from '@/layouts/LuminousPublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Box, ShieldCheck, Sparkles } from 'lucide-vue-next';
</script>

<template>
    <Head title="Welcome" />

    <LuminousPublicLayout>
        <section class="mx-auto max-w-5xl px-4 pt-12 pb-16 sm:px-6 sm:pt-18">
            <div class="luminous-panel overflow-hidden px-6 py-8 sm:px-8 sm:py-10">
                <p class="text-[0.72rem] font-semibold tracking-[0.24em] text-primary/75 uppercase">Fallback Welcome</p>
                <h1 class="text-gradient-filament mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                    A consistent front door for the print queue.
                </h1>
                <p class="text-muted-soft mt-5 max-w-2xl text-base leading-7">
                    This page is not part of the main route flow, but it now follows the same Luminous dark-first system as the rest of the app so no
                    legacy starter styling remains.
                </p>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <Link :href="$page.props.auth.user ? route('dashboard') : route('login')" class="pill-button pill-button-primary">
                        {{ $page.props.auth.user ? 'Open dashboard' : 'Request login link' }}
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link :href="route('home')" class="pill-button pill-button-secondary"> Go to landing page </Link>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="luminous-panel px-5 py-6">
                    <ShieldCheck class="h-5 w-5 text-primary" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-white">Invite-first</h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">
                        The same access model applies here: invited users, magic links, and a low-friction flow.
                    </p>
                </div>
                <div class="luminous-panel px-5 py-6">
                    <Box class="h-5 w-5 text-secondary" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-white">Request-centric</h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">
                        The visual language stays centered on files, queue status, and quick mobile actions.
                    </p>
                </div>
                <div class="luminous-panel px-5 py-6">
                    <Sparkles class="h-5 w-5 text-primary" />
                    <h2 class="mt-4 font-display text-xl font-semibold text-white">Dark-only system</h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">
                        No starter-kit light theme remains on this fallback page or the active app screens.
                    </p>
                </div>
            </div>
        </section>
    </LuminousPublicLayout>
</template>
