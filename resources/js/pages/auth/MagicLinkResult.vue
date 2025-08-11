<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
  <Head title="Magic Link" />
  <div class="min-h-screen bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100">
    <div class="mx-auto max-w-lg px-6 py-12">
      <header class="mb-6">
        <h1 class="text-2xl font-semibold">Magic link</h1>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
          Your one-time magic login link is valid for 10 minutes and can only be used once.
        </p>
      </header>

      <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
        <template v-if="$page.props.errors?.token || $page.props.errors?.email">
          <p class="text-sm text-red-600">This magic link is invalid or has expired. Please request a new one.</p>
          <Link :href="route('magic.request')" class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">Request new link</Link>
        </template>
        <template v-else>
          <p class="text-sm text-emerald-700 dark:text-emerald-400">Success! If you are not redirected automatically, continue to your dashboard.</p>
          <Link :href="route('dashboard')" class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">Go to dashboard</Link>
        </template>
      </div>

      <div class="mt-8 text-sm">
        <Link :href="route('home')" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">Back to home</Link>
      </div>
    </div>
  </div>
</template>
