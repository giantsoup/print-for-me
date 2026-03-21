<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import logoUrl from '../../../images/website-logo.png';

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

  <div class="relative min-h-screen overflow-hidden text-white">
    <!-- Synthwave background -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-[#0b002b] via-[#12002f] to-[#340058]" />
      <div class="absolute inset-x-0 bottom-0 h-1/2 [background:radial-gradient(80%_50%_at_50%_120%,rgba(255,0,204,0.6),transparent_70%)]" />
      <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.09)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.09)_1px,transparent_1px)]; [background-size:40px_40px]; [background-position:center]" />
    </div>

    <!-- Top navigation -->
    <header class="relative z-10 flex items-center justify-between px-6 py-4">
      <div class="flex items-center gap-3">
        <img :src="logoUrl" alt="Taylor's Print Services logo" class="h-8 w-8 rounded-md object-contain ring-1 ring-white/10 bg-black/40 md:h-10 md:w-10" height="40" width="40" loading="eager" decoding="async" />
        <span class="text-sm font-semibold tracking-wider text-white/80">Taylor's Print Services</span>
      </div>
      <nav class="flex items-center gap-3 text-sm">
        <Link :href="route('home')" class="text-white/80 hover:text-white">Home</Link>
        <Link :href="route('logout')" method="post" as="button" class="text-white/80 hover:text-white">Log out</Link>
      </nav>
    </header>

    <!-- Content -->
    <main class="relative z-10 mx-auto max-w-lg px-6 pb-24 pt-10">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <h1 class="text-xl font-semibold">Verify email</h1>
        <p class="mt-2 text-sm text-white/80">Please verify your email address by clicking on the link we just emailed to you.</p>

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-emerald-300">
          A new verification link has been sent to the email address you provided during registration.
        </div>

        <form @submit.prevent="submit" class="mt-6 space-y-4 text-center">
          <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center gap-2 rounded-md bg-fuchsia-600/90 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90 disabled:opacity-60">
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            <span>Resend verification email</span>
          </button>

          <Link :href="route('logout')" method="post" as="button" class="mx-auto block text-sm text-white/80 hover:text-white">Log out</Link>
        </form>
      </section>
    </main>
  </div>
</template>
