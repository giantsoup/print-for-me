<script setup lang="ts">
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatFileSize } from '@/lib/prints';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Upload, WandSparkles } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    constraints: {
        maxFiles: number;
        maxTotalBytes: number;
        allowedExtensions: string[];
    };
}

const props = defineProps<Props>();

const form = useForm<{ source_url: string | null; instructions: string | null; files: File[] | null }>({
    source_url: '',
    instructions: '',
    files: [],
});

const pickedFiles = ref<File[]>([]);

function onPickFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const list = input.files ? Array.from(input.files) : [];
    pickedFiles.value = list;
    form.files = list;
}

const fileCount = computed(() => pickedFiles.value.length);
const totalBytes = computed(() => pickedFiles.value.reduce((sum, file) => sum + (file.size || 0), 0));
const totalSize = computed(() => formatFileSize(totalBytes.value));
const hasUrl = computed(() => Boolean(form.source_url?.trim()));
const hasFiles = computed(() => fileCount.value > 0);
const withinCount = computed(() => fileCount.value <= props.constraints.maxFiles);
const withinTotal = computed(() => totalBytes.value <= props.constraints.maxTotalBytes);
const hasSource = computed(() => hasUrl.value || hasFiles.value);
const canSubmit = computed(() => withinCount.value && withinTotal.value && hasSource.value && !form.processing);

function submit() {
    if (!canSubmit.value) {
        return;
    }

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

    <LuminousAppLayout
        active-nav="new"
        eyebrow="New Request"
        title="Upload first, then dial in the details."
        intro="This flow is built for mobile: source link, files, instructions, and limits all stay visible before you submit."
        :show-dock="false"
    >
        <form class="grid gap-6 xl:grid-cols-[1.2fr_0.8fr]" @submit.prevent="submit">
            <section class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <label for="source_url" class="field-label">Source Link</label>
                    <input
                        id="source_url"
                        v-model="form.source_url"
                        type="url"
                        placeholder="https://printables.com/model/..."
                        class="luminous-input"
                    />
                    <p v-if="form.errors.source_url" class="mt-2 text-sm text-rose-300">{{ form.errors.source_url }}</p>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <label for="files" class="field-label">Upload Files</label>
                    <label
                        for="files"
                        class="block cursor-pointer rounded-[1.7rem] border border-dashed border-white/10 bg-white/[0.03] px-5 py-8 transition-colors hover:bg-white/[0.05]"
                    >
                        <div class="mx-auto flex max-w-sm flex-col items-center text-center">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary">
                                <Upload class="h-6 w-6" />
                            </div>
                            <h2 class="mt-5 font-display text-2xl font-semibold tracking-tight text-white">Drag, drop, or tap to browse.</h2>
                            <p class="text-muted-soft mt-3 text-sm leading-6">
                                Upload STL, 3MF, OBJ, F3D, F3Z, STEP, STP, IGES, or IGS files. The request needs either files or a source link.
                            </p>
                            <div
                                class="mt-5 flex flex-wrap items-center justify-center gap-2 text-[0.7rem] font-semibold tracking-[0.18em] text-white/45 uppercase"
                            >
                                <span class="rounded-full bg-white/[0.05] px-3 py-2">Max {{ props.constraints.maxFiles }} files</span>
                                <span class="rounded-full bg-white/[0.05] px-3 py-2">50 MB total</span>
                            </div>
                        </div>
                    </label>
                    <input id="files" type="file" multiple class="sr-only" @change="onPickFiles" />

                    <div class="mt-4 space-y-3">
                        <div
                            v-for="file in pickedFiles"
                            :key="file.name + file.size"
                            class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-3 text-sm"
                        >
                            <div class="min-w-0">
                                <p class="truncate font-medium text-white">{{ file.name }}</p>
                                <p class="mt-1 text-xs tracking-[0.16em] text-white/42 uppercase">{{ formatFileSize(file.size) }}</p>
                            </div>
                        </div>

                        <p v-if="form.errors.files" class="text-sm text-rose-300">{{ form.errors.files }}</p>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <label for="instructions" class="field-label">Instructions</label>
                    <textarea
                        id="instructions"
                        v-model="form.instructions"
                        rows="6"
                        placeholder="Material, infill, color, timing, tolerances, or anything else the print queue should know."
                        class="luminous-textarea"
                    />
                    <p v-if="form.errors.instructions" class="mt-2 text-sm text-rose-300">{{ form.errors.instructions }}</p>
                </article>
            </section>

            <aside class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Request Summary</p>
                    <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Everything needed to submit.</h2>

                    <dl class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Files selected</dt>
                            <dd class="font-display text-2xl text-white">{{ fileCount }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Current total</dt>
                            <dd class="font-display text-2xl text-secondary">{{ totalSize }}</dd>
                        </div>
                        <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                            <dt class="text-muted-soft text-sm">Source provided</dt>
                            <dd class="text-sm font-semibold tracking-[0.18em] uppercase" :class="hasSource ? 'text-primary' : 'text-white/45'">
                                {{ hasSource ? 'Ready' : 'Required' }}
                            </dd>
                        </div>
                    </dl>

                    <div class="text-muted-soft mt-6 space-y-2 text-sm leading-6">
                        <p v-if="!withinCount">You have selected more than {{ props.constraints.maxFiles }} files.</p>
                        <p v-if="!withinTotal">The total upload size is above 50 MB.</p>
                        <p v-if="!hasSource">Add a source link or upload at least one file before submitting.</p>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-secondary/12 text-secondary">
                            <WandSparkles class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="font-display text-xl font-semibold tracking-tight text-white">Helpful tips</h2>
                            <ul class="text-muted-soft mt-4 space-y-3 text-sm leading-6">
                                <li>Use the instructions field for materials, finish, or support preferences.</li>
                                <li>Keep the source link when the original model page has context worth preserving.</li>
                                <li>Pending requests can still be edited after submission.</li>
                            </ul>
                        </div>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <button
                        type="submit"
                        :disabled="!canSubmit"
                        class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                        {{ form.processing ? 'Submitting request' : 'Submit request' }}
                    </button>
                    <p class="mt-3 text-center text-xs tracking-[0.18em] text-white/40 uppercase">Files stay private to this request.</p>
                </article>
            </aside>
        </form>
    </LuminousAppLayout>
</template>
