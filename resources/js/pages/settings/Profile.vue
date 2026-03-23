<script setup lang="ts">
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import type { User } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ShieldAlert } from 'lucide-vue-next';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
}

defineProps<Props>();

const page = usePage();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
});

function submit() {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Profile settings" />

    <LuminousAppLayout
        active-nav="settings"
        eyebrow="Settings"
        title="Profile and session controls."
        intro="Keep your contact details current and use the security actions here if a session ever looks wrong."
        :show-dock="false"
    >
        <form class="grid gap-6 xl:grid-cols-[1fr_0.78fr]" @submit.prevent="submit">
            <section class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <label for="name" class="field-label">Name</label>
                    <input id="name" v-model="form.name" type="text" required autocomplete="name" class="luminous-input" />
                    <p v-if="form.errors.name" class="mt-2 text-sm text-rose-300">{{ form.errors.name }}</p>

                    <div class="mt-6">
                        <label for="email" class="field-label">Email Address</label>
                        <input id="email" v-model="form.email" type="email" required autocomplete="email" class="luminous-input" />
                        <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300">{{ form.errors.email }}</p>
                    </div>

                    <div
                        v-if="mustVerifyEmail && !user.email_verified_at"
                        class="text-muted-soft mt-6 rounded-[1.3rem] bg-white/[0.04] px-4 py-4 text-sm leading-6"
                    >
                        Your email is unverified.
                        <Link :href="route('verification.send')" method="post" as="button" class="font-medium text-primary">
                            Resend verification email
                        </Link>
                    </div>

                    <p v-if="status === 'verification-link-sent'" class="mt-4 rounded-[1.2rem] bg-primary/10 px-4 py-3 text-sm text-primary">
                        A new verification link has been sent.
                    </p>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        {{ form.processing ? 'Saving profile' : 'Save profile' }}
                    </button>
                    <p v-if="form.recentlySuccessful" class="mt-3 text-center text-sm text-primary">Saved.</p>
                </article>
            </section>

            <aside class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Last Session</p>
                    <dl class="mt-6 space-y-4 text-sm">
                        <div class="rounded-[1.3rem] bg-white/[0.04] px-4 py-4">
                            <dt class="text-white/45">IP address</dt>
                            <dd class="mt-2 font-medium text-white">{{ user.last_login_ip ?? 'Unavailable' }}</dd>
                        </div>
                        <div class="rounded-[1.3rem] bg-white/[0.04] px-4 py-4">
                            <dt class="text-white/45">Device</dt>
                            <dd class="mt-2 break-words text-white/78">{{ user.last_login_user_agent ?? 'Unavailable' }}</dd>
                        </div>
                    </dl>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-rose-500/12 text-rose-300">
                            <ShieldAlert class="h-4 w-4" />
                        </div>
                        <div>
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-rose-200/80 uppercase">Session Security</p>
                            <p class="text-muted-soft mt-3 text-sm leading-6">
                                If a session looks compromised, invalidate every active session attached to this account.
                            </p>
                        </div>
                    </div>

                    <Link
                        :href="route('sessions.invalidate')"
                        method="post"
                        as="button"
                        class="pill-button mt-6 w-full justify-center bg-rose-500/12 text-rose-300"
                    >
                        Log out of all devices
                    </Link>
                </article>
            </aside>
        </form>
    </LuminousAppLayout>
</template>
