<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import TopNav from '@/components/TopNav.vue';
import { LoaderCircle } from 'lucide-vue-next';
import { onMounted } from 'vue';

// We include two anti-bot fields alongside the email so the server can
// cheaply filter obvious automation without affecting legitimate users.
// - website: honeypot input that humans won't fill; bots often will.
// - form_started_at: client timestamp to enforce a minimum fill-time.
const form = useForm({
  email: '',
  website: '', // honeypot: must remain empty; filled => treat as bot (see controller)
  form_started_at: '', // timestamp (ms) set on mount to detect instant submissions
});

function submit() {
  form.post(route('magic.send'), {
    preserveScroll: true,
    onSuccess: () => form.reset('email'),
  });
}

onMounted(() => {
  // Capture when the form became interactable (ms). Bots often post immediately.
  form.form_started_at = String(Date.now());
});

</script>

<template>
  <Head title="Log in" />

  <div class="relative min-h-screen overflow-hidden text-white">
    <!-- Synthwave background -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-[#0b002b] via-[#12002f] to-[#340058]" />
      <div class="absolute inset-x-0 bottom-0 h-1/2 [background:radial-gradient(80%_50%_at_50%_120%,rgba(255,0,204,0.6),transparent_70%)]" />
      <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.09)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.09)_1px,transparent_1px)]; [background-size:40px_40px]; [background-position:center]" />
    </div>

    <!-- Top navigation -->
    <TopNav />

    <!-- Content -->
    <main class="relative z-10 mx-auto max-w-lg px-6 pb-24 pt-10">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <h1 class="text-xl font-semibold">Sign in with a magic link</h1>
        <p class="mt-2 text-sm text-white/80">
          Invite-first access. Enter your email and we’ll send a one‑time sign‑in link. It expires in 10 minutes.
        </p>
        <p class="mt-1 text-xs text-white/60">
          Rate-limited for security.
        </p>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
          <div>
            <label for="email" class="mb-1 block text-sm font-medium text-white/90">Email address</label>
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
              class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40"
            />
            <p v-if="form.errors.email" class="mt-1 text-sm text-pink-300">{{ form.errors.email }}</p>
          </div>

          <!-- Anti-bot honeypot and timing fields: hidden from users, visible to naive bots -->
          <div class="hidden" aria-hidden="true">
            <label for="website">Website</label>
            <input id="website" v-model="form.website" type="text" name="website" tabindex="-1" autocomplete="off" />
            <!-- Timestamp in ms; server uses it for a minimum fill-time heuristic -->
            <input type="hidden" name="form_started_at" :value="form.form_started_at" />
          </div>

          <button
            type="submit"
            :disabled="form.processing"
            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-fuchsia-600/90 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90 disabled:opacity-60"
          >
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            <span>{{ form.processing ? 'Sending…' : 'Send magic link' }}</span>
          </button>

          <p v-if="$page.props.flash?.status" class="text-sm text-emerald-300">{{ $page.props.flash?.status }}</p>
        </form>
      </section>

      <p class="mt-4 text-center text-xs text-white/70">
        Having trouble? Contact the admin for an invite.
      </p>
    </main>
  </div>
</template>
