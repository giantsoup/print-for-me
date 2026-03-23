<script setup lang="ts">
import LuminousFocusedLayout from '@/layouts/LuminousFocusedLayout.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, History } from 'lucide-vue-next';

const page = usePage();
const hasError = Boolean(
    (page.props.errors as Record<string, string[] | string | undefined>)?.token ||
    (page.props.errors as Record<string, string[] | string | undefined>)?.email,
);
</script>

<template>
    <Head title="Magic Link" />

    <LuminousFocusedLayout
        :eyebrow="hasError ? 'Link Expired' : 'Access Confirmed'"
        :title="hasError ? 'This magic link is no longer active.' : 'You are on the way into the queue.'"
        :intro="
            hasError
                ? 'For security, magic links are temporary and single-use. Request a fresh link to continue.'
                : 'Your login was accepted. If you are not redirected automatically, continue into your dashboard.'
        "
        :back-href="route('home')"
        back-label="Back home"
    >
        <div class="flex flex-col items-center text-center">
            <div
                class="flex h-18 w-18 items-center justify-center rounded-full"
                :class="hasError ? 'bg-rose-500/10 text-rose-300' : 'bg-primary/12 text-primary'"
            >
                <History v-if="hasError" class="h-7 w-7" />
                <CheckCircle2 v-else class="h-7 w-7" />
            </div>

            <p
                class="mt-6 max-w-md rounded-[1.2rem] px-4 py-4 text-sm leading-6"
                :class="hasError ? 'bg-rose-500/10 text-rose-200' : 'bg-primary/10 text-primary'"
            >
                <template v-if="hasError">This magic link is invalid or has expired. Request a new one to continue.</template>
                <template v-else>Success. Continue to your dashboard if the redirect does not happen automatically.</template>
            </p>

            <Link
                :href="hasError ? route('magic.request') : route('dashboard')"
                class="pill-button mt-6"
                :class="hasError ? 'pill-button-secondary' : 'pill-button-primary'"
            >
                {{ hasError ? 'Request new link' : 'Go to dashboard' }}
                <ArrowRight class="h-4 w-4" />
            </Link>
        </div>
    </LuminousFocusedLayout>
</template>
