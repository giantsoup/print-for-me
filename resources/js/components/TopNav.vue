<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Menu, X } from 'lucide-vue-next';

const page = usePage();
const isAuthed = computed(() => Boolean((page.props as any).auth && (page.props as any).auth.user));
const logoUrl = new URL('../../images/website-logo.png', import.meta.url).href;
const open = ref(false);

function toggle() {
  open.value = !open.value;
}
function close() {
  open.value = false;
}
</script>

<template>
  <!-- Sticky header -->
  <header class="sticky top-0 z-20 flex items-center justify-between border-b border-white/10 bg-black/20 px-4 py-3 backdrop-blur sm:px-6">
    <div class="flex items-center gap-3">
      <img :src="logoUrl" alt="Taylor's Print Services logo" class="h-8 w-8 rounded-md object-contain ring-1 ring-white/10 bg-black/40 md:h-10 md:w-10" height="40" width="40" loading="eager" decoding="async" />
      <span class="text-sm font-semibold tracking-wider text-white/80">Taylor's Print Services</span>
    </div>

    <!-- Desktop nav -->
    <nav class="hidden items-center gap-3 text-sm md:flex">
      <template v-if="isAuthed">
        <Link :href="route('dashboard')" class="text-white/80 hover:text-white">Dashboard</Link>
        <Link :href="route('print-requests.index')" class="text-white/80 hover:text-white">My requests</Link>
        <Link :href="route('print-requests.create')" class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-1.5 font-semibold text-white shadow hover:bg-fuchsia-500/90">New request</Link>
        <Link :href="route('profile.edit')" class="text-white/80 hover:text-white">Profile</Link>
        <Link method="post" :href="route('logout')" as="button" class="text-white/70 hover:text-white/90">Log out</Link>
      </template>
      <template v-else>
        <Link :href="route('home')" class="text-white/80 hover:text-white">Home</Link>
        <Link :href="route('login')" class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-1.5 font-semibold text-white shadow hover:bg-fuchsia-500/90">Log in</Link>
      </template>
    </nav>

    <!-- Mobile actions: primary CTA + hamburger -->
    <div class="flex items-center gap-2 md:hidden">
      <template v-if="isAuthed">
        <Link :href="route('print-requests.create')" class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-1.5 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90" @click="close">
          New request
        </Link>
      </template>
      <template v-else>
        <Link :href="route('login')" class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-1.5 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90" @click="close">
          Log in
        </Link>
      </template>
      <button type="button" class="inline-flex items-center rounded-md border border-white/15 bg-white/10 p-2 text-white/80 hover:text-white" :aria-expanded="open ? 'true' : 'false'" aria-controls="mobile-menu" @click="toggle">
        <span class="sr-only">Toggle navigation</span>
        <Menu v-if="!open" class="h-5 w-5" />
        <X v-else class="h-5 w-5" />
      </button>
    </div>
  </header>

  <!-- Mobile menu panel -->
  <div v-show="open" id="mobile-menu" class="z-10 border-b border-white/10 bg-black/30 px-4 py-3 backdrop-blur md:hidden sm:px-6">
    <nav class="grid gap-2 text-sm">
      <template v-if="isAuthed">
        <Link :href="route('print-requests.index')" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-white/90 hover:bg-white/15" @click="close">My requests</Link>
        <Link :href="route('dashboard')" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-white/90 hover:bg-white/15" @click="close">Dashboard</Link>
        <Link :href="route('profile.edit')" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-white/90 hover:bg-white/15" @click="close">Profile</Link>
        <Link method="post" :href="route('logout')" as="button" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-left text-white/80 hover:bg-white/15" @click="close">Log out</Link>
      </template>
      <template v-else>
        <Link :href="route('home')" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-white/90 hover:bg-white/15" @click="close">Home</Link>
        <Link :href="route('login')" class="block rounded-md border border-white/15 bg-white/10 px-3 py-2 text-white/90 hover:bg-white/15" @click="close">Log in</Link>
      </template>
    </nav>
  </div>
</template>
