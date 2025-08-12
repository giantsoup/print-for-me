<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import TopNav from '@/components/TopNav.vue';

interface RecentRequest {
  id: number
  status: string
  created_at: string
}

interface Props {
  recentRequests: RecentRequest[]
  isAdmin: boolean
}

const props = defineProps<Props>();

function statusClass(status: string) {
  // Dark-friendly, glassy badges similar to the overall aesthetic
  switch (status) {
    case 'pending':
      return 'bg-amber-400/20 text-amber-200 ring-1 ring-inset ring-amber-300/30';
    case 'accepted':
      return 'bg-sky-400/20 text-sky-200 ring-1 ring-inset ring-sky-300/30';
    case 'printing':
      return 'bg-indigo-400/20 text-indigo-200 ring-1 ring-inset ring-indigo-300/30';
    case 'complete':
      return 'bg-emerald-400/20 text-emerald-200 ring-1 ring-inset ring-emerald-300/30';
    default:
      return 'bg-zinc-400/20 text-zinc-200 ring-1 ring-inset ring-zinc-300/30';
  }
}
</script>

<template>
  <Head title="Dashboard" />

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
      <!-- Actions -->
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <Link :href="route('print-requests.index')" class="inline-flex items-center rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm font-medium text-white/90 backdrop-blur hover:bg-white/15 hover:text-white">
          My Print Requests
        </Link>
        <Link :href="route('print-requests.create')" class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90">
          New Request
        </Link>
      </div>

      <!-- Recent Requests -->
      <section class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-semibold text-white">Recent Requests</h2>
          <Link :href="route('print-requests.index')" class="text-sm text-white/80 hover:text-white">View all</Link>
        </div>
        <div v-if="props.recentRequests?.length">
          <ul class="divide-y divide-white/10">
            <li v-for="r in props.recentRequests" :key="r.id" class="flex items-center justify-between py-2">
              <div class="min-w-0 flex items-center gap-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{ r.status }}</span>
                <Link :href="route('print-requests.show', { print_request: r.id })" class="truncate text-sm text-white/90 hover:text-white">Request #{{ r.id }}</Link>
              </div>
              <div class="text-xs text-white/70">
                {{ new Date(r.created_at).toLocaleString() }}
              </div>
            </li>
          </ul>
        </div>
        <p v-else class="text-sm text-white/80">No recent requests yet. Create your first one.</p>
      </section>
    </main>
  </div>
</template>
