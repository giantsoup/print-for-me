<script setup lang="ts">
import LuminousFocusedLayout from '@/layouts/LuminousFocusedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
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
        onSuccess: () => form.reset('email'),
    });
}

onMounted(() => {
    form.form_started_at = String(Date.now());
});
</script>

<template>
    <Head title="Log in" />

    <LuminousFocusedLayout
        eyebrow="Magic Link Access"
        title="Request a secure login link."
        intro="Invite-only access keeps the app lightweight. Enter your email and we will send a one-time link that expires in 10 minutes."
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
                    autofocus
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
        </form>

        <div class="text-muted-soft mt-8 rounded-[1.4rem] bg-white/[0.04] px-4 py-4 text-sm leading-6">
            Rate-limited for security. If your email is not already invited, contact the admin before requesting access again.
        </div>
    </LuminousFocusedLayout>
</template>
