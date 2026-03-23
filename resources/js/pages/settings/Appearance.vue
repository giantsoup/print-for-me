<script setup lang="ts">
import { useInterfacePreferences, type MotionPreference } from '@/composables/useInterfacePreferences';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Gauge, Sparkles } from 'lucide-vue-next';

const { motionPreference, updateMotionPreference } = useInterfacePreferences();

const options: Array<{ value: MotionPreference; title: string; description: string }> = [
    {
        value: 'standard',
        title: 'Standard motion',
        description: 'Keep the full Luminous transitions, hover states, and ambient movement.',
    },
    {
        value: 'reduced',
        title: 'Reduced motion',
        description: 'Tone down transitions and animation while keeping the same dark visual system.',
    },
];
</script>

<template>
    <Head title="Interface preferences" />

    <LuminousAppLayout
        active-nav="settings"
        eyebrow="Interface Preferences"
        title="Dark-only visuals, with motion tuned to your comfort."
        intro="The redesign stays on one dark visual system. This screen now controls motion intensity instead of light and dark themes."
        :show-dock="false"
    >
        <div class="grid gap-6 xl:grid-cols-[0.92fr_1.08fr]">
            <section class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Motion Preference</p>
                    <div class="mt-6 space-y-3">
                        <button
                            v-for="option in options"
                            :key="option.value"
                            type="button"
                            class="w-full rounded-[1.45rem] px-4 py-4 text-left transition-colors"
                            :class="
                                motionPreference === option.value ? 'bg-primary/10 text-white' : 'bg-white/[0.04] text-white/78 hover:bg-white/[0.06]'
                            "
                            @click="updateMotionPreference(option.value)"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-display text-xl font-semibold tracking-tight">{{ option.title }}</p>
                                    <p class="text-muted-soft mt-2 text-sm leading-6">{{ option.description }}</p>
                                </div>
                                <span
                                    class="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full border"
                                    :class="motionPreference === option.value ? 'border-primary bg-primary' : 'border-white/18'"
                                >
                                    <span class="h-2 w-2 rounded-full bg-[#072714]" />
                                </span>
                            </div>
                        </button>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary/12 text-secondary">
                            <Gauge class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-display text-xl font-semibold tracking-tight text-white">What changed</h2>
                            <p class="text-muted-soft mt-3 text-sm leading-6">
                                The app no longer switches between light, dark, and system themes. The visual system is fixed so every screen stays
                                consistent with the new mobile-first redesign.
                            </p>
                        </div>
                    </div>
                </article>
            </section>

            <section class="luminous-panel px-5 py-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Live Preview</p>
                        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">See the interface feel in context.</h2>
                    </div>
                    <Sparkles class="h-5 w-5 text-primary" />
                </div>

                <div class="mt-6 space-y-4">
                    <div class="relative overflow-hidden rounded-[1.7rem] bg-white/[0.04] px-5 py-6">
                        <div
                            class="absolute -top-6 -right-6 h-24 w-24 rounded-full bg-primary/18 blur-3xl"
                            :class="motionPreference === 'standard' ? 'animate-pulse' : ''"
                        />
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Primary Action</p>
                        <button class="pill-button pill-button-primary mt-5">Submit request</button>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-[1.45rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-white/42 uppercase">Card motion</p>
                            <div
                                class="mt-4 rounded-[1.3rem] bg-white/[0.05] px-4 py-5"
                                :class="motionPreference === 'standard' ? 'transition-transform duration-500 hover:-translate-y-1' : ''"
                            >
                                <p class="font-display text-xl font-semibold tracking-tight text-white">Queue card</p>
                                <p class="text-muted-soft mt-2 text-sm leading-6">
                                    Hover and tap feedback scale back automatically in reduced motion mode.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-[1.45rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-white/42 uppercase">Ambient effects</p>
                            <div class="mt-4 rounded-[1.3rem] bg-white/[0.05] px-4 py-5">
                                <div class="h-2 rounded-full bg-primary/25">
                                    <div
                                        class="h-2 rounded-full bg-primary"
                                        :class="motionPreference === 'standard' ? 'w-2/3 transition-all duration-700' : 'w-2/3'"
                                    />
                                </div>
                                <p class="text-muted-soft mt-3 text-sm leading-6">
                                    Reduced motion keeps the same layout and styling while minimizing animated transitions.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </LuminousAppLayout>
</template>
