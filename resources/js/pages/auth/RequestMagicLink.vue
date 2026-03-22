<script setup lang="ts">
import LuminousFocusedLayout from '@/layouts/LuminousFocusedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight, LoaderCircle } from 'lucide-vue-next';
import { onMounted } from 'vue';

const form = useForm({
    email: '',
    website: '',
    form_started_at: '',
});

function submit() {
    form.post(route('magic.send'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('email');
        },
    });
}

onMounted(() => {
    form.form_started_at = String(Date.now());
});
</script>

<template>
    <Head title="Request Magic Link" />

    <LuminousFocusedLayout
        eyebrow="Access Request"
        title="Need a fresh magic link?"
        intro="Use this flow when you were already invited but need another one-time sign-in link. It is valid for 10 minutes and can only be used once."
        :back-href="route('home')"
        back-label="Back home"
    >
        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <label for="email" class="field-label">Email Address</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    autocapitalize="none"
                    spellcheck="false"
                    inputmode="email"
                    placeholder="you@example.com"
                    class="luminous-input"
                />
                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300">{{ form.errors.email }}</p>
            </div>

            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
                <input type="hidden" name="form_started_at" :value="form.form_started_at" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
            >
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span>{{ form.processing ? 'Sending link' : 'Send magic link' }}</span>
                <ArrowRight class="h-4 w-4" />
            </button>

            <p v-if="$page.props.flash?.status" class="rounded-[1.2rem] bg-primary/10 px-4 py-3 text-sm text-primary">
                {{ $page.props.flash?.status }}
            </p>
            <p v-if="$page.props.errors?.session" class="rounded-[1.2rem] bg-rose-500/10 px-4 py-3 text-sm text-rose-300">
                {{ $page.props.errors.session }}
            </p>
        </form>

        <p class="mt-8 text-center text-sm leading-6 text-muted-soft">
            If you do not have access yet, return to the landing page and contact the admin for an invite.
        </p>
        <div class="mt-4 text-center">
            <Link :href="route('home')" class="text-sm font-medium text-white/70 hover:text-white">Back to home</Link>
        </div>
    </LuminousFocusedLayout>
</template>
