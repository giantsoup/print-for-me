<script setup lang="ts">
import LuminousPublicLayout from '@/layouts/LuminousPublicLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Boxes, Clock3, FileUp, ShieldCheck } from 'lucide-vue-next';
</script>

<template>
    <Head title="Home" />

    <LuminousPublicLayout>
        <section class="mx-auto max-w-6xl px-4 pb-16 pt-10 sm:px-6 sm:pt-16">
            <div class="grid gap-8 lg:grid-cols-[1.2fr_0.8fr] lg:items-center">
                <div class="relative overflow-hidden rounded-[2rem] bg-[linear-gradient(135deg,rgba(24,26,31,0.96),rgba(18,20,24,0.88))] px-6 py-8 shadow-[0_30px_80px_rgba(0,0,0,0.35)] sm:px-8 sm:py-10">
                    <div class="absolute -right-20 -top-20 h-48 w-48 rounded-full bg-primary/12 blur-3xl" />
                    <div class="absolute bottom-0 left-1/3 h-32 w-32 rounded-full bg-secondary/10 blur-3xl" />

                    <p class="mb-4 text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-primary/80">
                        Invite-Only Workshop
                    </p>
                    <h1 class="font-display text-4xl font-semibold tracking-tight text-gradient-filament sm:text-5xl lg:text-6xl">
                        Request a print from your phone, then follow it through the queue.
                    </h1>
                    <p class="mt-5 max-w-2xl text-base leading-7 text-muted-soft sm:text-lg">
                        Print for Me keeps the workflow lightweight: send a link or upload files, add a few instructions, and
                        track each request from pending to pickup without the clutter of a heavy dashboard.
                    </p>

                    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                        <Link :href="route('login')" class="pill-button pill-button-primary">
                            Request login link
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                        <a href="#how-it-works" class="pill-button pill-button-secondary">
                            See how it works
                        </a>
                    </div>

                    <div class="mt-10 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-white/45">Formats</p>
                            <p class="mt-2 text-sm font-medium text-white/86">STL, 3MF, OBJ, STEP, IGES</p>
                        </div>
                        <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-white/45">Limits</p>
                            <p class="mt-2 text-sm font-medium text-white/86">10 files, 50 MB total</p>
                        </div>
                        <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.7rem] font-semibold uppercase tracking-[0.22em] text-white/45">Access</p>
                            <p class="mt-2 text-sm font-medium text-white/86">Magic-link only</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="luminous-panel px-5 py-5">
                        <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary">
                            <FileUp class="h-5 w-5" />
                        </div>
                        <h2 class="font-display text-2xl font-semibold tracking-tight text-white">Upload first</h2>
                        <p class="mt-3 text-sm leading-6 text-muted-soft">
                            The request flow is built around files and source links, so the most important step is the easiest one
                            to reach on mobile.
                        </p>
                    </div>

                    <div class="luminous-panel px-5 py-5">
                        <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-secondary/12 text-secondary">
                            <Clock3 class="h-5 w-5" />
                        </div>
                        <h2 class="font-display text-2xl font-semibold tracking-tight text-white">Clear status trail</h2>
                        <p class="mt-3 text-sm leading-6 text-muted-soft">
                            Every request moves through a simple queue: pending, accepted, printing, and complete.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <section id="how-it-works" class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
            <div class="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.24em] text-primary/75">How It Works</p>
                    <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight text-white">A simple mobile-first queue</h2>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="luminous-panel px-5 py-6">
                    <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/[0.05] text-primary">01</div>
                    <h3 class="font-display text-xl font-semibold text-white">Request access</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Enter your email and receive a one-time login link. No password setup and no extra onboarding.
                    </p>
                </div>

                <div class="luminous-panel px-5 py-6">
                    <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/[0.05] text-primary">02</div>
                    <h3 class="font-display text-xl font-semibold text-white">Send a request</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Upload files or paste a source URL, add any printing notes, and submit directly from the same screen.
                    </p>
                </div>

                <div class="luminous-panel px-5 py-6">
                    <div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-white/[0.05] text-primary">03</div>
                    <h3 class="font-display text-xl font-semibold text-white">Track progress</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Follow updates in one place and get notified when a request is accepted, printing, or ready.
                    </p>
                </div>
            </div>
        </section>

        <section id="why-it-works" class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-10">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div class="luminous-panel px-5 py-6">
                    <ShieldCheck class="h-5 w-5 text-primary" />
                    <h3 class="mt-4 font-display text-xl font-semibold text-white">Private by default</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Requests and files stay tied to the invited account that submitted them.
                    </p>
                </div>

                <div class="luminous-panel px-5 py-6">
                    <Boxes class="h-5 w-5 text-secondary" />
                    <h3 class="mt-4 font-display text-xl font-semibold text-white">Built for real files</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        The upload flow supports the common 3D model formats people already use.
                    </p>
                </div>

                <div class="luminous-panel px-5 py-6">
                    <Clock3 class="h-5 w-5 text-primary" />
                    <h3 class="mt-4 font-display text-xl font-semibold text-white">Low-friction updates</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Pending requests can be edited before they move deeper into the queue.
                    </p>
                </div>

                <div class="luminous-panel px-5 py-6">
                    <FileUp class="h-5 w-5 text-secondary" />
                    <h3 class="mt-4 font-display text-xl font-semibold text-white">Fast from mobile</h3>
                    <p class="mt-3 text-sm leading-6 text-muted-soft">
                        Primary actions stay large, close to thumb reach, and easy to scan on small screens.
                    </p>
                </div>
            </div>
        </section>
    </LuminousPublicLayout>
</template>
