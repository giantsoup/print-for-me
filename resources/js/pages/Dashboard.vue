<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

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

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: '/dashboard' },
];

function statusClass(status: string) {
  switch (status) {
    case 'pending':
      return 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-200';
    case 'accepted':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-200';
    case 'printing':
      return 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-200';
    case 'complete':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200';
    default:
      return 'bg-zinc-100 text-zinc-800 dark:bg-zinc-900/30 dark:text-zinc-200';
  }
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4 overflow-x-auto">
      <div class="flex items-center gap-3">
        <a :href="route('print-requests.index')" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">My Print Requests</a>
        <a :href="route('print-requests.create')" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">New Request</a>
      </div>

      <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
        <div class="mb-3 flex items-center justify-between">
          <h2 class="text-sm font-medium">Recent Requests</h2>
          <Link :href="route('print-requests.index')" class="text-sm text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">View all</Link>
        </div>
        <div v-if="props.recentRequests?.length">
          <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
            <li v-for="r in props.recentRequests" :key="r.id" class="flex items-center justify-between py-2">
              <div class="min-w-0 flex items-center gap-3">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(r.status)">{{ r.status }}</span>
                <Link :href="route('print-requests.show', { print_request: r.id })" class="truncate text-sm">Request #{{ r.id }}</Link>
              </div>
              <div class="text-xs text-zinc-500">
                {{ new Date(r.created_at).toLocaleString() }}
              </div>
            </li>
          </ul>
        </div>
        <p v-else class="text-sm text-zinc-600 dark:text-zinc-400">No recent requests yet. Create your first one.</p>
      </div>
    </div>
  </AppLayout>
</template>
