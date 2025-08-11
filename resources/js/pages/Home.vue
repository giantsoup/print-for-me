<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type PageProps = {
  auth?: { user?: any };
};

const page = usePage<PageProps>();
const isAuthed = computed(() => Boolean(page.props.auth && (page.props.auth as any).user));
</script>

<template>
  <Head title="Home" />

  <div class="relative min-h-screen overflow-hidden">
    <!-- Synthwave background (from former Variant 1) -->
    <div aria-hidden="true" class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-gradient-to-br from-[#0b002b] via-[#12002f] to-[#340058]" />
      <div class="absolute inset-x-0 bottom-0 h-1/2 [background:radial-gradient(80%_50%_at_50%_120%,rgba(255,0,204,0.6),transparent_70%)]" />
      <div class="absolute inset-0 opacity-30 [background-image:linear-gradient(rgba(255,255,255,.09)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,.09)_1px,transparent_1px)]; [background-size:40px_40px]; [background-position:center]" />
    </div>

    <!-- Top navigation -->
    <header class="relative z-10 flex items-center justify-between px-6 py-4">
      <div class="flex items-center gap-3">
        <span class="inline-flex h-8 w-8 items-center justify-center rounded bg-black/40 ring-1 ring-white/10">
          <span class="h-3 w-3 rounded-full bg-[#ff00e6] shadow-[0_0_12px_#ff00e6]" />
        </span>
        <span class="text-sm font-semibold tracking-wider text-white/80">Taylor's Print Services</span>
      </div>
      <nav class="flex items-center gap-3 text-sm">
        <Link v-if="isAuthed" :href="route('dashboard')" class="text-white/80 hover:text-white">Dashboard</Link>
        <template v-else>
          <Link :href="route('magic.request')" class="rounded-md bg-white/10 px-3 py-1.5 text-white backdrop-blur hover:bg-white/20">Request magic link</Link>
          <Link :href="route('login')" class="text-white/80 hover:text-white">Log in</Link>
        </template>
      </nav>
    </header>

    <!-- Content -->
    <main class="relative z-10 mx-auto max-w-6xl px-6 pb-24 pt-10 text-white">
      <section class="flex flex-col items-center text-center">
        <h1
          class="text-balance bg-clip-text text-5xl font-extrabold tracking-tight text-transparent sm:text-6xl [text-shadow:0_0_40px_rgba(255,0,204,.35)]"
          style="background-image: linear-gradient(135deg,#22d3ee,#a78bfa,#f472b6);"
        >
          Taylor’s Print Services
        </h1>
        <p class="mt-4 max-w-2xl text-pretty text-white/80">
          Synthwave UI. Serious backend. Your models, our printers, zero passwords.
        </p>

        <div class="mt-14 grid w-full grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Queued notifications</p>
            <p class="mt-1 text-xs text-white/60">All the right emails at the right time.</p>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Admin controls</p>
            <p class="mt-1 text-xs text-white/60">Revert, accept, print, complete—tightly scoped.</p>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Retention</p>
            <p class="mt-1 text-xs text-white/60">Automatic cleanups and warnings at 83/90 days.</p>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>
