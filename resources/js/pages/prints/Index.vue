<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

interface PrintRequestFile {
  id: number
  original_name: string
  size_bytes: number
}

interface PrintRequestItem {
  id: number
  status: string
  source_url?: string | null
  instructions?: string | null
  created_at?: string
  files?: PrintRequestFile[]
}

interface Props {
  items: {
    data: PrintRequestItem[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    links: { url: string | null; label: string; active: boolean }[]
  }
  isAdmin: boolean
  filters: { status?: string | null }
  statuses: string[]
}

const props = defineProps<Props>();

const breadcrumbs = [
  { title: 'Print Requests', href: '/print-requests' },
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

function filterByStatus(status: string | null) {
  const query: Record<string, string> = {};
  if (status) query.status = status;
  router.get(route('print-requests.index'), query, { preserveState: true, preserveScroll: true });
}

function adminAction(action: 'accept' | 'printing' | 'complete' | 'revert', id: number) {
  let routeName = '';
  switch (action) {
    case 'accept':
      routeName = 'admin.print-requests.accept';
      break;
    case 'printing':
      routeName = 'admin.print-requests.printing';
      break;
    case 'complete':
      routeName = 'admin.print-requests.complete';
      break;
    case 'revert':
      routeName = 'admin.print-requests.revert';
      break;
  }
  router.patch(route(routeName, { print_request: id }), {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => router.reload({ only: ['items'] }),
  });
}
</script>

<template>
  <Head title="Print Requests" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4 space-y-4">
      <div class="flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold">Print Requests</h1>
        <Link :href="route('print-requests.create')"
              class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white hover:bg-zinc-800 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">
          New Request
        </Link>
      </div>

      <div v-if="props.isAdmin" class="flex items-center gap-2">
        <label class="text-sm">Filter by status</label>
        <select class="rounded-md border border-zinc-300 bg-white px-3 py-1.5 text-sm dark:border-zinc-700 dark:bg-zinc-900" :value="props.filters.status || ''" @change="(e:any) => filterByStatus(e.target.value || null)">
          <option value="">All</option>
          <option v-for="s in props.statuses" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
        <table class="min-w-full text-sm">
          <thead class="bg-zinc-50 dark:bg-zinc-900/50">
            <tr>
              <th class="px-3 py-2 text-left">ID</th>
              <th class="px-3 py-2 text-left">Status</th>
              <th class="px-3 py-2 text-left">Files</th>
              <th class="px-3 py-2 text-left">Created</th>
              <th class="px-3 py-2 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in props.items.data" :key="item.id" class="border-t border-zinc-100 dark:border-zinc-800">
              <td class="px-3 py-2">#{{ item.id }}</td>
              <td class="px-3 py-2">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ item.status }}</span>
              </td>
              <td class="px-3 py-2">{{ item.files?.length || 0 }}</td>
              <td class="px-3 py-2">{{ item.created_at }}</td>
              <td class="px-3 py-2 space-x-2">
                <Link :href="route('print-requests.show', { print_request: item.id })" class="text-zinc-900 underline dark:text-zinc-100">View</Link>
                <template v-if="props.isAdmin">
                  <button class="text-blue-700 underline disabled:opacity-50" @click="adminAction('accept', item.id)" :disabled="item.status !== 'pending'">Accept</button>
                  <button class="text-indigo-700 underline disabled:opacity-50" @click="adminAction('printing', item.id)" :disabled="item.status !== 'accepted'">Set Printing</button>
                  <button class="text-emerald-700 underline disabled:opacity-50" @click="adminAction('complete', item.id)" :disabled="item.status !== 'printing'">Complete</button>
                  <button class="text-amber-700 underline disabled:opacity-50" @click="adminAction('revert', item.id)" :disabled="!(item.status === 'accepted' || item.status === 'printing')">Revert to Pending</button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <nav v-if="props.items.links?.length" class="flex items-center gap-1">
        <Link v-for="l in props.items.links" :key="l.label + l.url" :href="l.url || '#'" class="px-2 py-1 rounded-md text-sm"
              :class="l.active ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'text-zinc-700 dark:text-zinc-300'">
          <span v-html="l.label"></span>
        </Link>
      </nav>
    </div>
  </AppLayout>
</template>
