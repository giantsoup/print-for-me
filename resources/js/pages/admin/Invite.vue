<script setup lang="ts">
import LuminousFocusedLayout from '@/layouts/LuminousFocusedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Send } from 'lucide-vue-next';

const form = useForm({
    email: '',
});

function submit() {
    form.post(route('admin.invite.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('email'),
    });
}
</script>

<template>
    <Head title="Invite User" />

    <LuminousFocusedLayout eyebrow="Users" title="Invite user" :back-href="route('admin.users.index')" back-label="Users">
        <form class="space-y-6" @submit.prevent="submit">
            <div>
                <label for="email" class="field-label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    placeholder="name@example.com"
                    class="luminous-input"
                />
                <p v-if="form.errors.email" class="mt-2 text-sm text-rose-300">{{ form.errors.email }}</p>
            </div>

            <div class="text-muted-soft rounded-[1.4rem] bg-white/[0.04] px-4 py-4 text-sm leading-6">
                Sends a one-time sign-in link and grants access.
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
            >
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                <span>{{ form.processing ? 'Sending invite' : 'Send invite' }}</span>
                <Send class="h-4 w-4" />
            </button>

            <p v-if="$page.props.flash?.status" class="rounded-[1.2rem] bg-primary/10 px-4 py-3 text-sm text-primary">
                {{ $page.props.flash?.status }}
            </p>
        </form>
    </LuminousFocusedLayout>
</template>
