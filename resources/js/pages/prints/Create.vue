<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
  constraints: {
    maxFiles: number
    maxTotalBytes: number
    allowedExtensions: string[]
  }
}

const props = defineProps<Props>();

const breadcrumbs = [
  { title: 'Print Requests', href: '/print-requests' },
  { title: 'New', href: '/print-requests/create' },
];

const form = useForm<{ source_url: string | null; instructions: string | null; files: File[] | null }>({
  source_url: '',
  instructions: '',
  files: [],
});

const pickedFiles = ref<File[]>([]);

function onPickFiles(e: Event) {
  const input = e.target as HTMLInputElement;
  const list = input.files ? Array.from(input.files) : [];
  pickedFiles.value = list;
  form.files = list;
}

const fileCount = computed(() => pickedFiles.value.length);
const totalBytes = computed(() => pickedFiles.value.reduce((acc, f) => acc + (f.size || 0), 0));
const totalMB = computed(() => (totalBytes.value / (1024 * 1024)).toFixed(2));

const hasUrl = computed(() => !!form.source_url && form.source_url.trim().length > 0);
const hasFiles = computed(() => fileCount.value > 0);

const withinCount = computed(() => fileCount.value <= props.constraints.maxFiles);
const withinTotal = computed(() => totalBytes.value <= props.constraints.maxTotalBytes);
const hasSource = computed(() => hasUrl.value || hasFiles.value);

const canSubmit = computed(() => withinCount.value && withinTotal.value && hasSource.value && !form.processing);

function submit() {
  if (!canSubmit.value) return;
  form.post(route('print-requests.store'), {
    forceFormData: true,
    onSuccess: () => {
      pickedFiles.value = [];
      form.reset();
    },
  });
}
</script>

<template>
  <Head title="New Print Request" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="p-4 space-y-4">
      <div class="flex items-center justify-between">
        <h1 class="text-xl font-semibold">New Print Request</h1>
        <Link :href="route('print-requests.index')" class="text-sm text-zinc-700 underline dark:text-zinc-300">Back to list</Link>
      </div>

      <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
        <p class="text-sm text-zinc-600 dark:text-zinc-400">
          At least one source is required: enter a source URL or upload files. Allowed extensions: {{ props.constraints.allowedExtensions.join(', ') }}. Per-file size up to 50 MB. Max files {{ props.constraints.maxFiles }}. Total size ≤ 50 MB.
        </p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label for="source_url" class="mb-1 block text-sm font-medium">Source URL</label>
          <input id="source_url" v-model="form.source_url" type="url" placeholder="https://..." class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900" />
          <p v-if="form.errors.source_url" class="mt-1 text-sm text-red-600">{{ form.errors.source_url }}</p>
        </div>

        <div>
          <label for="files" class="mb-1 block text-sm font-medium">Files</label>
          <input id="files" type="file" multiple @change="onPickFiles" class="block w-full rounded-md border border-dashed border-zinc-300 bg-white px-3 py-6 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900" />
          <div class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
            <span>{{ fileCount }} files selected; Total {{ totalMB }} MB</span>
            <span v-if="!withinCount" class="ml-2 text-red-600">Max {{ props.constraints.maxFiles }} files.</span>
            <span v-if="!withinTotal" class="ml-2 text-red-600">Total exceeds 50 MB.</span>
            <span v-if="!hasSource" class="ml-2 text-red-600">Provide a URL or at least one file.</span>
          </div>
          <p v-if="form.errors.files" class="mt-1 text-sm text-red-600">{{ form.errors.files }}</p>
        </div>

        <div>
          <label for="instructions" class="mb-1 block text-sm font-medium">Instructions</label>
          <textarea id="instructions" v-model="form.instructions" rows="5" class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm outline-none focus:border-zinc-500 focus:ring-0 dark:border-zinc-700 dark:bg-zinc-900" />
          <p v-if="form.errors.instructions" class="mt-1 text-sm text-red-600">{{ form.errors.instructions }}</p>
        </div>

        <button type="submit" :disabled="!canSubmit" class="inline-flex items-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-zinc-800 disabled:opacity-60 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200">
          {{ form.processing ? 'Submitting…' : 'Submit Request' }}
        </button>
      </form>
    </div>
  </AppLayout>
</template>
