<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import TopNav from '@/components/TopNav.vue';

interface Props {
  constraints: {
    maxFiles: number
    maxTotalBytes: number
    allowedExtensions: string[]
  }
}

const props = defineProps<Props>();

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
    <main class="relative z-10 mx-auto max-w-3xl px-6 pb-24 pt-10">
      <section class="rounded-lg border border-white/10 bg-white/5 p-6 backdrop-blur">
        <h1 class="text-xl font-semibold">New Print Request</h1>
        <p class="mt-2 text-sm text-white/80">
          At least one source is required: enter a source URL or upload files. Allowed extensions: {{ props.constraints.allowedExtensions.join(', ') }}.
        </p>
        <p class="mt-1 text-xs text-white/60">
          Per-file size up to 50 MB. Max files {{ props.constraints.maxFiles }}. Total size ≤ 50 MB.
        </p>

        <form @submit.prevent="submit" class="mt-6 space-y-4">
          <div>
            <label for="source_url" class="mb-1 block text-sm font-medium text-white/90">Source URL</label>
            <input
              id="source_url"
              v-model="form.source_url"
              type="url"
              placeholder="https://..."
              class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40"
            />
            <p v-if="form.errors.source_url" class="mt-1 text-sm text-pink-300">{{ form.errors.source_url }}</p>
          </div>

          <div>
            <label for="files" class="mb-1 block text-sm font-medium text-white/90">Files</label>
            <input
              id="files"
              type="file"
              multiple
              @change="onPickFiles"
              class="block w-full cursor-pointer rounded-md border border-dashed border-white/20 bg-white/10 px-3 py-6 text-sm text-white outline-none transition focus:border-white/40"
            />
            <div class="mt-2 text-sm text-white/70">
              <span>{{ fileCount }} files selected; Total {{ totalMB }} MB</span>
              <span v-if="!withinCount" class="ml-2 text-pink-300">Max {{ props.constraints.maxFiles }} files.</span>
              <span v-if="!withinTotal" class="ml-2 text-pink-300">Total exceeds 50 MB.</span>
              <span v-if="!hasSource" class="ml-2 text-pink-300">Provide a URL or at least one file.</span>
            </div>
            <p v-if="form.errors.files" class="mt-1 text-sm text-pink-300">{{ form.errors.files }}</p>
          </div>

          <div>
            <label for="instructions" class="mb-1 block text-sm font-medium text-white/90">Instructions</label>
            <textarea
              id="instructions"
              v-model="form.instructions"
              rows="5"
              class="block w-full rounded-md border border-white/20 bg-white/10 px-3 py-2 text-sm text-white placeholder-white/40 outline-none ring-0 transition focus:border-white/40"
            />
            <p v-if="form.errors.instructions" class="mt-1 text-sm text-pink-300">{{ form.errors.instructions }}</p>
          </div>

          <button
            type="submit"
            :disabled="!canSubmit"
            class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-fuchsia-600/90 px-4 py-2 text-sm font-semibold text-white shadow hover:bg-fuchsia-500/90 disabled:opacity-60"
          >
            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
            <span>{{ form.processing ? 'Submitting…' : 'Submit Request' }}</span>
          </button>
        </form>
      </section>

      <p class="mt-4 text-center text-xs text-white/70">
        You can update or add files after creating the request while it's pending.
      </p>
    </main>
  </div>
</template>
