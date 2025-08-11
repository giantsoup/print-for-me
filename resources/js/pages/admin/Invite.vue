<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const breadcrumbs = [
  { title: 'Admin', href: '/admin' },
  { title: 'Invite User', href: '/admin/invite' },
];

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
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4 space-y-6">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Invite a user</h1>
      </div>

      <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
        <p>
          Enter the user's email to invite them. They'll receive a one-time magic login link that expires in 10 minutes.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4 max-w-lg">
        <div>
          <label for="email" class="mb-1 block text-sm font-medium">Email</label>
          <input id="email" v-model="form.email" type="email" required autocomplete="email" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900" />
          <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
        </div>

        <button type="submit" :disabled="form.processing" class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">
          {{ form.processing ? 'Inviting…' : 'Send Invite' }}
        </button>

        <p v-if="$page.props.flash?.status" class="text-sm text-emerald-600">{{ $page.props.flash?.status }}</p>
      </form>
    </div>
  </AppLayout>
</template>
