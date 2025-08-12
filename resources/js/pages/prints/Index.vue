<script setup lang="ts">
import TopNav from '@/components/TopNav.vue';
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


function statusClass(status: string) {
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
    <main class="relative z-10 mx-auto max-w-6xl px-6 pb-24 pt-6">
      <div class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 class="text-xl font-semibold">Print Requests</h1>
        <Link :href="route('print-requests.create')"
              class="inline-flex items-center rounded-md bg-fuchsia-600/90 px-3 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90">
          New Request
        </Link>
      </div>

      <div v-if="props.isAdmin" class="mb-4 flex flex-wrap items-center gap-2">
        <label class="text-sm text-white/80">Filter by status</label>
        <select class="rounded-md border border-white/20 bg-white/10 px-3 py-1.5 text-sm text-white outline-none transition focus:border-white/40" :value="props.filters.status || ''" @change="(e:any) => filterByStatus(e.target.value || null)">
          <option value="">All</option>
          <option v-for="s in props.statuses" :key="s" :value="s">{{ s }}</option>
        </select>
      </div>

      <section class="rounded-lg border border-white/10 bg-white/5 p-0 backdrop-blur">
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 text-left text-white/80">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Status</th>
                <th class="px-3 py-2">Files</th>
                <th class="px-3 py-2">Created</th>
                <th class="px-3 py-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in props.items.data" :key="item.id" class="border-t border-white/10">
                <td class="px-3 py-2">#{{ item.id }}</td>
                <td class="px-3 py-2">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(item.status)">{{ item.status }}</span>
                </td>
                <td class="px-3 py-2">{{ item.files?.length || 0 }}</td>
                <td class="px-3 py-2">{{ item.created_at }}</td>
                <td class="px-3 py-2 space-x-2">
                  <Link :href="route('print-requests.show', { print_request: item.id })" class="text-white/90 underline hover:text-white">View</Link>
                  <template v-if="props.isAdmin">
                    <button class="text-sky-300 underline disabled:opacity-50" @click="adminAction('accept', item.id)" :disabled="item.status !== 'pending'">Accept</button>
                    <button class="text-indigo-300 underline disabled:opacity-50" @click="adminAction('printing', item.id)" :disabled="item.status !== 'accepted'">Set Printing</button>
                    <button class="text-emerald-300 underline disabled:opacity-50" @click="adminAction('complete', item.id)" :disabled="item.status !== 'printing'">Complete</button>
                    <button class="text-amber-300 underline disabled:opacity-50" @click="adminAction('revert', item.id)" :disabled="!(item.status === 'accepted' || item.status === 'printing')">Revert</button>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <nav v-if="props.items.links?.length" class="mt-4 flex flex-wrap items-center gap-1">
        <Link v-for="l in props.items.links" :key="l.label + l.url" :href="l.url || '#'" class="rounded-md px-2 py-1 text-sm"
              :class="l.active ? 'bg-white/10 text-white' : 'text-white/80 hover:text-white'">
          <span v-html="l.label"></span>
        </Link>
      </nav>
    </main>
  </div>
</template>
