<script setup lang="ts">
import TopNav from '@/components/TopNav.vue';
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
  <Head :title="`Request #${props.printRequest.id}`" />

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
      <div class="mb-4 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-center">
        <h1 class="text-xl font-semibold">Request #{{ props.printRequest.id }}</h1>
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium" :class="statusClass(props.printRequest.status)">{{ props.printRequest.status }}</span>
      </div>

      <div class="grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2 space-y-4">
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <h2 class="mb-2 text-sm font-semibold text-white">Details</h2>
            <div class="space-y-3">
              <div>
                <label for="source_url" class="mb-1 block text-sm font-medium text-white/90">Source URL</label>
                <input id="source_url" :disabled="!props.can.update" v-model="form.source_url" type="url" placeholder="https://..." class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40 disabled:opacity-60" />
                <p v-if="form.errors.source_url" class="mt-1 text-sm text-pink-300">{{ form.errors.source_url }}</p>
              </div>
              <div>
                <label for="instructions" class="mb-1 block text-sm font-medium text-white/90">Instructions</label>
                <textarea id="instructions" :disabled="!props.can.update" v-model="form.instructions" rows="6" class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40 disabled:opacity-60" />
                <p v-if="form.errors.instructions" class="mt-1 text-sm text-pink-300">{{ form.errors.instructions }}</p>
              </div>
            </div>
          </div>

          <div class="rounded-lg border border-white/10 bg-white/5 p-4 backdrop-blur">
            <h2 class="mb-2 text-sm font-semibold text-white">Files</h2>
            <div class="space-y-3">
              <ul class="divide-y divide-white/10">
                <li v-for="f in props.printRequest.files" :key="f.id" class="flex items-center justify-between py-2">
                  <div class="min-w-0">
                    <div class="truncate text-sm text-white/90">{{ f.original_name }}</div>
                    <div class="text-xs text-white/60">{{ (f.size_bytes / (1024*1024)).toFixed(2) }} MB</div>
                  </div>
                  <div class="flex items-center gap-3">
                    <a :href="route('print-requests.files.download', { print_request: props.printRequest.id, file: f.id })" class="text-sm text-white/90 underline hover:text-white">Download</a>
                    <label v-if="props.can.update" class="inline-flex items-center gap-2 text-sm text-white/80">
                      <input type="checkbox" :checked="form.remove_file_ids.includes(f.id)" @change="(e:any) => toggleRemove(f.id, e.target.checked)" />
                      <span>Remove</span>
                    </label>
                  </div>
                </li>
              </ul>

              <div v-if="props.can.update" class="mt-2">
                <label for="new_files" class="mb-1 block text-sm font-medium text-white/90">Add files</label>
                <input id="new_files" type="file" multiple @change="onPickFiles" class="block w-full cursor-pointer rounded-md border border-dashed border-white/20 bg-white/10 px-3 py-6 text-sm text-white outline-none transition focus:border-white/40" />
                <div class="mt-2 text-sm text-white/70">
                  <span>{{ finalCount }} files after save; Total {{ finalMB }} MB</span>
                  <span v-if="!withinCount" class="ml-2 text-pink-300">Max {{ props.constraints.maxFiles }} files.</span>
                  <span v-if="!withinTotal" class="ml-2 text-pink-300">Total exceeds 50 MB.</span>
                  <span v-if="!hasSource" class="ml-2 text-pink-300">Provide a URL or at least one file.</span>
                </div>
                <p v-if="form.errors.files" class="mt-1 text-sm text-pink-300">{{ form.errors.files }}</p>
              </div>
            </div>
          </div>
        </section>

        <aside class="space-y-4">
          <div class="rounded-lg border border-white/10 bg-white/5 p-4 text-sm backdrop-blur">
            <div class="mb-3 font-semibold">Actions</div>
            <div class="grid gap-2 sm:flex sm:flex-wrap sm:items-center">
              <button v-if="props.can.update" type="button" :disabled="!canSave" @click="submit" class="inline-flex w-full items-center justify-center rounded-md bg-fuchsia-600/90 px-3 py-2 font-semibold text-white shadow hover:bg-fuchsia-500/90 disabled:opacity-60 sm:w-auto">Save Changes</button>
              <button v-if="props.can.delete" type="button" @click="cancelRequest" class="inline-flex w-full items-center justify-center rounded-md bg-red-600/90 px-3 py-2 font-semibold text-white shadow hover:bg-red-600 sm:w-auto">Cancel Request</button>
            </div>
          </div>

          <div class="rounded-lg border border-white/10 bg-white/5 p-4 text-xs text-white/70 backdrop-blur">
            <p>Allowed extensions: {{ props.constraints.allowedExtensions.join(', ') }}</p>
            <p>Per-file size up to 50 MB. Max files {{ props.constraints.maxFiles }}. Total size ≤ 50 MB.</p>
          </div>
        </aside>
      </div>
    </main>
  </div>
</template>
