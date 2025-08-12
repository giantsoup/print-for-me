<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import TopNav from '@/components/TopNav.vue';
</script>

<template>
  <Head title="Appearance settings" />

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
    <main class="relative z-10 mx-auto max-w-5xl px-6 pb-24 pt-6">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <SettingsLayout>
          <div class="space-y-6">
            <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
            <AppearanceTabs />
          </div>
        </SettingsLayout>
      </section>
    </main>
  </div>
</template>
