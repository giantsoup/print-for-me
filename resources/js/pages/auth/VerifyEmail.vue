<script setup lang="ts">
import LuminousFocusedLayout from '@/layouts/LuminousFocusedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle, MailCheck } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({});

function submit() {
    form.post(route('verification.send'));
}
</script>

<template>
    <Head title="Email verification" />

    <LuminousFocusedLayout
        eyebrow="Verify Email"
        title="Confirm this email before using the queue."
        intro="Click the link from your inbox to finish verification. You can request another email from this screen if you need one."
        :back-href="route('home')"
        back-label="Back home"
    >
        <div class="flex flex-col items-center text-center">
            <div class="flex h-18 w-18 items-center justify-center rounded-full bg-primary/12 text-primary">
                <MailCheck class="h-7 w-7" />
            </div>

            <p class="mt-6 max-w-md text-sm leading-6 text-muted-soft">
                A verification link has been sent to your email address. Open it from your inbox to activate the account attached to this magic-link session.
            </p>

            <p
                v-if="status === 'verification-link-sent'"
                class="mt-5 rounded-[1.2rem] bg-primary/10 px-4 py-3 text-sm text-primary"
            >
                A new verification link has been sent.
            </p>

            <form class="mt-6 w-full max-w-sm space-y-3" @submit.prevent="submit">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                >
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Resend verification email
                </button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="pill-button pill-button-secondary w-full"
                >
                    Log out
                </Link>
            </form>
        </div>
    </LuminousFocusedLayout>
</template>
