<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

interface Props {
  token: string;
  email: string;
}

const props = defineProps<Props>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

function submit() {
  form.post(route('password.store'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation');
    },
  });
}

const logoUrl = new URL('../../images/website-logo.png', import.meta.url).href;
</script>

<template>
  <Head title="Reset password" />

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
        <Link :href="route('login')" class="text-white/80 hover:text-white">Log in</Link>
      </nav>
    </header>

    <!-- Content -->
    <main class="relative z-10 mx-auto max-w-lg px-6 pb-24 pt-10">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <h1 class="text-xl font-semibold">Reset password</h1>
        <p class="mt-2 text-sm text-white/80">Please enter your new password below.</p>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
          <div>
            <label for="email" class="mb-1 block text-sm font-medium text-white/90">Email</label>
            <input id="email" v-model="form.email" type="email" autocomplete="email" readonly class="block w-full cursor-not-allowed rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white/70 outline-none ring-0 transition" />
            <p v-if="form.errors.email" class="mt-1 text-sm text-pink-300">{{ form.errors.email }}</p>
          </div>

          <div>
            <label for="password" class="mb-1 block text-sm font-medium text-white/90">Password</label>
            <input id="password" v-model="form.password" type="password" autocomplete="new-password" autofocus placeholder="Password" class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40" />
            <p v-if="form.errors.password" class="mt-1 text-sm text-pink-300">{{ form.errors.password }}</p>
          </div>

          <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-white/90">Confirm password</label>
            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" placeholder="Confirm password" class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40" />
            <p v-if="form.errors.password_confirmation" class="mt-1 text-sm text-pink-300">{{ form.errors.password_confirmation }}</p>
          </div>

          <button type="submit" :disabled="form.processing" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-fuchsia-600/90 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90 disabled:opacity-60">
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            <span>Reset password</span>
          </button>
        </form>
      </section>
    </main>
  </div>
</template>
