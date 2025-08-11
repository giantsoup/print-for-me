<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type PageProps = {
  auth?: { user?: any };
};

const page = usePage<PageProps>();
const isAuthed = computed(() => Boolean(page.props.auth && (page.props.auth as any).user));
const logoUrl = new URL('../../images/website-logo.png', import.meta.url).href;
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
        <img :src="logoUrl" alt="Taylor's Print Services logo" class="h-8 w-8 rounded-md object-contain ring-1 ring-white/10 bg-black/40 md:h-10 md:w-10" height="40" width="40" loading="eager" decoding="async" />
        <span class="text-sm font-semibold tracking-wider text-white/80">Taylor's Print Services</span>
      </div>
      <nav class="flex items-center gap-3 text-sm">
        <Link v-if="isAuthed" :href="route('dashboard')" class="text-white/80 hover:text-white">Dashboard</Link>
        <template v-else>
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
          Get your 3D parts printed—fast and private
        </h1>
        <p class="mt-4 max-w-2xl text-pretty text-white/80">
          Upload your CAD files, track progress, and get notified at every step. Passwordless magic‑link sign‑in—no passwords to remember.
        </p>

        <div class="mt-14 grid w-full grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Private uploads</p>
            <p class="mt-1 text-xs text-white/60">Your files stay private with secure, owner‑only access.</p>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Track progress</p>
            <p class="mt-1 text-xs text-white/60">See status move from Pending → Accepted → Printing → Complete.</p>
          </div>
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <p class="text-sm font-semibold">Email updates</p>
            <p class="mt-1 text-xs text-white/60">We’ll email when it’s accepted, if we need info, and when it’s complete.</p>
          </div>
        </div>
        <p class="mt-6 max-w-2xl text-center text-xs text-white/60">
          Supported formats: STL, 3MF, OBJ, F3D/F3Z, STEP/STP, IGES/IGS. Up to 10 files, total 50&nbsp;MB per request.
        </p>
      </section>
    </main>
  </div>
</template>
