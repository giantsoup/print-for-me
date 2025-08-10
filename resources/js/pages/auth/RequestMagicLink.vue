<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
  email: '',
});

function submit() {
  form.post(route('magic.send'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset('email');
    },
  });
}
</script>

<template>
  <Head title="Request Magic Link" />

  <div class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="mx-auto max-w-lg px-6 py-12">
      <header class="mb-6">
        <h1 class="text-2xl font-semibold">Request a magic login link</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          Invite-first access. Your email must be whitelisted. The link expires in 10 minutes and can only be used once.
        </p>
      </header>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label for="email" class="mb-1 block text-sm font-medium">Email</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            required
            autocomplete="email"
            class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900"
            placeholder="you@example.com"
          />
          <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
        >
          {{ form.processing ? 'Sending…' : 'Send magic link' }}
        </button>

        <p v-if="$page.props.flash?.status" class="text-sm text-emerald-600">{{ $page.props.flash?.status }}</p>
        <p v-if="$page.props.errors?.session" class="text-sm text-red-600">{{ $page.props.errors.session }}</p>
      </form>

      <div class="mt-8 text-sm">
        <Link :href="route('home')" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">Back to home</Link>
      </div>
    </div>
  </div>
</template>
