<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface FileItem { id: number; original_name: string; size_bytes: number }

interface Props {
  printRequest: {
    id: number
    status: string
    source_url?: string | null
    instructions?: string | null
    files: FileItem[]
    deleted_at?: string | null
  }
  can: {
    update: boolean
    delete: boolean
    isAdmin: boolean
  }
  constraints: {
    maxFiles: number
    maxTotalBytes: number
    allowedExtensions: string[]
  }
}

const props = defineProps<Props>();

const breadcrumbs = [
  { title: 'Print Requests', href: '/print-requests' },
  { title: `#${props.printRequest.id}`, href: `/print-requests/${props.printRequest.id}` },
];

const form = useForm<{ source_url: string | null; instructions: string | null; files: File[]; remove_file_ids: number[] }>({
  source_url: props.printRequest.source_url || '',
  instructions: props.printRequest.instructions || '',
  files: [],
  remove_file_ids: [],
});

const pickedFiles = ref<File[]>([]);
function onPickFiles(e: Event) {
  const input = e.target as HTMLInputElement;
  const list = input.files ? Array.from(input.files) : [];
  pickedFiles.value = list;
  form.files = list;
}

function toggleRemove(id: number, checked: boolean) {
  if (checked) {
    if (!form.remove_file_ids.includes(id)) form.remove_file_ids.push(id);
  } else {
    form.remove_file_ids = form.remove_file_ids.filter((x) => x !== id);
  }
}

const existingCount = computed(() => props.printRequest.files.length);
const existingSize = computed(() => props.printRequest.files.reduce((a, f) => a + (f.size_bytes || 0), 0));
const removingCount = computed(() => props.printRequest.files.filter((f) => form.remove_file_ids.includes(f.id)).length);
const removingSize = computed(() => props.printRequest.files.filter((f) => form.remove_file_ids.includes(f.id)).reduce((a, f) => a + (f.size_bytes || 0), 0));
const newCount = computed(() => pickedFiles.value.length);
const newSize = computed(() => pickedFiles.value.reduce((a, f) => a + (f.size || 0), 0));

const finalCount = computed(() => existingCount.value - removingCount.value + newCount.value);
const finalSize = computed(() => existingSize.value - removingSize.value + newSize.value);
const finalMB = computed(() => (finalSize.value / (1024 * 1024)).toFixed(2));

const hasUrlAfter = computed(() => {
  const candidate = form.source_url ?? '';
  return candidate.trim().length > 0;
});
const hasFilesAfter = computed(() => finalCount.value > 0);
const hasSource = computed(() => hasUrlAfter.value || hasFilesAfter.value);

const withinCount = computed(() => finalCount.value <= props.constraints.maxFiles);
const withinTotal = computed(() => finalSize.value <= props.constraints.maxTotalBytes);

const canSave = computed(() => props.can.update && withinCount.value && withinTotal.value && hasSource.value && !form.processing);

function submit() {
  if (!canSave.value) return;
  form.transform((data) => ({
    ...data,
  })).patch(route('print-requests.update', { print_request: props.printRequest.id }), {
    forceFormData: true,
  });
}

function cancelRequest() {
  if (!props.can.delete) return;
  if (!confirm('Are you sure you want to cancel this pending request?')) return;
  router.delete(route('print-requests.destroy', { print_request: props.printRequest.id }));
}

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
  <Head :title="`Request #${props.printRequest.id}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">Request #{{ props.printRequest.id }}</h1>
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(props.printRequest.status)">{{ props.printRequest.status }}</span>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
          <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <h2 class="mb-2 text-sm font-medium">Details</h2>
            <div class="space-y-3">
              <div>
                <label for="source_url" class="mb-1 block text-sm">Source URL</label>
                <input id="source_url" :disabled="!props.can.update" v-model="form.source_url" type="url" placeholder="https://..." class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900" />
                <p v-if="form.errors.source_url" class="mt-1 text-sm text-red-600">{{ form.errors.source_url }}</p>
              </div>
              <div>
                <label for="instructions" class="mb-1 block text-sm">Instructions</label>
                <textarea id="instructions" :disabled="!props.can.update" v-model="form.instructions" rows="6" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-900" />
                <p v-if="form.errors.instructions" class="mt-1 text-sm text-red-600">{{ form.errors.instructions }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <h2 class="mb-2 text-sm font-medium">Files</h2>
            <div class="space-y-3">
              <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                <li v-for="f in props.printRequest.files" :key="f.id" class="flex items-center justify-between py-2">
                  <div class="min-w-0">
                    <div class="truncate text-sm">{{ f.original_name }}</div>
                    <div class="text-xs text-zinc-500">{{ (f.size_bytes / (1024*1024)).toFixed(2) }} MB</div>
                  </div>
                  <div class="flex items-center gap-3">
                    <a :href="route('print-requests.files.download', { print_request: props.printRequest.id, file: f.id })" class="text-sm text-zinc-700 underline dark:text-zinc-300">Download</a>
                    <label v-if="props.can.update" class="inline-flex items-center gap-2 text-sm">
                      <input type="checkbox" :checked="form.remove_file_ids.includes(f.id)" @change="(e:any) => toggleRemove(f.id, e.target.checked)" />
                      <span>Remove</span>
                    </label>
                  </div>
                </li>
              </ul>

              <div v-if="props.can.update" class="mt-2">
                <label for="new_files" class="mb-1 block text-sm">Add files</label>
                <input id="new_files" type="file" multiple @change="onPickFiles" class="block w-full rounded-md border border-dashed border-zinc-300 bg-white px-3 py-6 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900" />
                <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                  <span>{{ finalCount }} files after save; Total {{ finalMB }} MB</span>
                  <span v-if="!withinCount" class="ml-2 text-red-600">Max {{ props.constraints.maxFiles }} files.</span>
                  <span v-if="!withinTotal" class="ml-2 text-red-600">Total exceeds 50 MB.</span>
                  <span v-if="!hasSource" class="ml-2 text-red-600">Provide a URL or at least one file.</span>
                </div>
                <p v-if="form.errors.files" class="mt-1 text-sm text-red-600">{{ form.errors.files }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div class="rounded-lg border border-zinc-200 p-4 text-sm dark:border-zinc-800">
            <div class="mb-2 font-medium">Actions</div>
            <div class="space-x-2">
              <button v-if="props.can.update" type="button" :disabled="!canSave" @click="submit" class="inline-flex items-center rounded-md bg-zinc-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">Save Changes</button>
              <button v-if="props.can.delete" type="button" @click="cancelRequest" class="inline-flex items-center rounded-md bg-red-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-red-700">Cancel Request</button>
            </div>
          </div>

          <div class="rounded-lg border border-zinc-200 p-4 text-xs text-zinc-600 dark:border-zinc-800 dark:text-zinc-400">
            <p>Allowed extensions: {{ props.constraints.allowedExtensions.join(', ') }}</p>
            <p>Per-file size up to 50 MB. Max files {{ props.constraints.maxFiles }}. Total size ≤ 50 MB.</p>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
