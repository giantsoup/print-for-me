<script setup lang="ts">
import PrintRequestStateActions from '@/components/luminous/PrintRequestStateActions.vue';
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateOnly, formatDateTime, formatFileSize, type PrintRequestActionKey } from '@/lib/prints';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Download, LoaderCircle, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface FileItem {
    id: number;
    original_name: string;
    size_bytes: number;
}

interface TimelineItem {
    key: string;
    label: string;
    description: string;
    at: string;
}

interface Props {
    printRequest: {
        id: number;
        status: string;
        source_url?: string | null;
        instructions?: string | null;
        files: FileItem[];
        created_at: string;
        deleted_at?: string | null;
        user?: {
            name: string;
            email: string;
        } | null;
    };
    can: {
        update: boolean;
        delete: boolean;
        isAdmin: boolean;
    };
    availableStatusActions: PrintRequestActionKey[];
    timeline: TimelineItem[];
    constraints: {
        maxFiles: number;
        maxTotalBytes: number;
        allowedExtensions: string[];
    };
}

const props = defineProps<Props>();
const page = usePage();

const form = useForm<{ source_url: string | null; instructions: string | null; files: File[]; remove_file_ids: number[] }>({
    source_url: props.printRequest.source_url || '',
    instructions: props.printRequest.instructions || '',
    files: [],
    remove_file_ids: [],
});

const pickedFiles = ref<File[]>([]);
const flashStatus = computed(() => page.props.flash?.status);

function onPickFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const list = input.files ? Array.from(input.files) : [];
    pickedFiles.value = list;
    form.files = list;
}

function toggleRemove(id: number, checked: boolean) {
    if (checked) {
        if (!form.remove_file_ids.includes(id)) {
            form.remove_file_ids.push(id);
        }

        return;
    }

    form.remove_file_ids = form.remove_file_ids.filter((value) => value !== id);
}

const existingCount = computed(() => props.printRequest.files.length);
const existingSize = computed(() => props.printRequest.files.reduce((sum, file) => sum + (file.size_bytes || 0), 0));
const removingCount = computed(() => props.printRequest.files.filter((file) => form.remove_file_ids.includes(file.id)).length);
const removingSize = computed(() =>
    props.printRequest.files.filter((file) => form.remove_file_ids.includes(file.id)).reduce((sum, file) => sum + (file.size_bytes || 0), 0),
);
const newCount = computed(() => pickedFiles.value.length);
const newSize = computed(() => pickedFiles.value.reduce((sum, file) => sum + (file.size || 0), 0));
const finalCount = computed(() => existingCount.value - removingCount.value + newCount.value);
const finalSize = computed(() => existingSize.value - removingSize.value + newSize.value);
const hasUrlAfter = computed(() => Boolean(form.source_url?.trim()));
const hasFilesAfter = computed(() => finalCount.value > 0);
const withinCount = computed(() => finalCount.value <= props.constraints.maxFiles);
const withinTotal = computed(() => finalSize.value <= props.constraints.maxTotalBytes);
const hasSource = computed(() => hasUrlAfter.value || hasFilesAfter.value);
const canSave = computed(() => props.can.update && withinCount.value && withinTotal.value && hasSource.value && !form.processing);

function submit() {
    if (!canSave.value) {
        return;
    }

    form.patch(route('print-requests.update', { print_request: props.printRequest.id }), {
        forceFormData: true,
    });
}

function cancelRequest() {
    if (!props.can.delete) {
        return;
    }

    if (!confirm('Are you sure you want to cancel this pending request?')) {
        return;
    }

    router.delete(route('print-requests.destroy', { print_request: props.printRequest.id }));
}
</script>

<template>
    <Head :title="`Request #${props.printRequest.id}`" />

    <LuminousAppLayout
        active-nav="requests"
        eyebrow="Request Detail"
        :title="`Request #${props.printRequest.id}`"
        :intro="
            props.can.isAdmin && props.printRequest.user
                ? `${props.printRequest.user.name} submitted this request on ${formatDateOnly(props.printRequest.created_at)}.`
                : `Created ${formatDateOnly(props.printRequest.created_at)}. Update details while the request is still pending.`
        "
        wide
        :show-dock="false"
    >
        <template #pageActions>
            <StatusBadge :status="props.printRequest.status" />
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.12fr_0.88fr]">
            <section class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex-1">
                            <label for="source_url" class="field-label">Source Link</label>
                            <input
                                id="source_url"
                                v-model="form.source_url"
                                :disabled="!props.can.update"
                                type="url"
                                placeholder="https://..."
                                class="luminous-input disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <p v-if="form.errors.source_url" class="mt-2 text-sm text-rose-300">{{ form.errors.source_url }}</p>
                        </div>

                        <div
                            v-if="props.can.isAdmin && props.printRequest.user"
                            class="text-muted-soft rounded-[1.4rem] bg-white/[0.04] px-4 py-4 text-sm lg:max-w-xs"
                        >
                            <p class="text-[0.72rem] font-semibold tracking-[0.2em] text-primary/70 uppercase">Submitted By</p>
                            <p class="mt-3 font-display text-xl font-semibold tracking-tight text-white">{{ props.printRequest.user.name }}</p>
                            <p class="mt-1 text-sm text-white/65">{{ props.printRequest.user.email }}</p>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label for="instructions" class="field-label">Instructions</label>
                        <textarea
                            id="instructions"
                            v-model="form.instructions"
                            :disabled="!props.can.update"
                            rows="6"
                            class="luminous-textarea disabled:cursor-not-allowed disabled:opacity-60"
                            placeholder="Describe material, finish, color, tolerances, or anything else the queue should know."
                        />
                        <p v-if="form.errors.instructions" class="mt-2 text-sm text-rose-300">{{ form.errors.instructions }}</p>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Files</p>
                            <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Current request assets.</h2>
                        </div>
                        <p class="text-muted-soft text-sm">{{ existingCount }} attached</p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <div v-for="file in props.printRequest.files" :key="file.id" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-white">{{ file.original_name }}</p>
                                    <p class="mt-1 text-xs tracking-[0.18em] text-white/42 uppercase">{{ formatFileSize(file.size_bytes) }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a
                                        :href="route('print-requests.files.download', { print_request: props.printRequest.id, file: file.id })"
                                        class="pill-button pill-button-secondary text-sm"
                                    >
                                        <Download class="h-4 w-4" />
                                        Download
                                    </a>
                                    <label
                                        v-if="props.can.update"
                                        class="inline-flex min-h-12 items-center gap-2 rounded-full border border-white/8 bg-white/[0.05] px-4 text-sm font-medium text-white/72"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/15 bg-transparent text-primary focus:ring-0"
                                            :checked="form.remove_file_ids.includes(file.id)"
                                            @change="(event: any) => toggleRemove(file.id, event.target.checked)"
                                        />
                                        Remove
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="props.can.update" class="mt-6">
                        <label for="new_files" class="field-label">Add Files</label>
                        <label
                            for="new_files"
                            class="text-muted-soft block cursor-pointer rounded-[1.45rem] border border-dashed border-white/10 bg-white/[0.03] px-5 py-6 text-sm transition-colors hover:bg-white/[0.05]"
                        >
                            Add more files to this request while it is still editable.
                        </label>
                        <input id="new_files" type="file" multiple class="sr-only" @change="onPickFiles" />

                        <div v-if="pickedFiles.length" class="mt-4 space-y-3">
                            <div v-for="file in pickedFiles" :key="file.name + file.size" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-3 text-sm">
                                <p class="font-medium text-white">{{ file.name }}</p>
                                <p class="mt-1 text-xs tracking-[0.18em] text-white/42 uppercase">{{ formatFileSize(file.size) }}</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>

            <aside class="space-y-6">
                <article v-if="props.can.isAdmin" class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Workflow Control</p>
                    <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Manage the request state here.</h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">
                        Move the request through review and production without leaving the detail screen.
                    </p>

                    <div class="mt-6">
                        <PrintRequestStateActions
                            :request-id="props.printRequest.id"
                            :status="props.printRequest.status"
                            :actions="props.availableStatusActions"
                        />
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Timeline</p>
                    <div class="mt-6 space-y-4">
                        <div v-for="event in props.timeline" :key="event.key" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-display text-xl font-semibold tracking-tight text-white">{{ event.label }}</p>
                                <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">
                                    {{ formatDateTime(event.at) }}
                                </span>
                            </div>
                            <p class="text-muted-soft mt-2 text-sm leading-6">{{ event.description }}</p>
                        </div>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Save State</p>
                    <div class="mt-6 space-y-3">
                        <div class="flex items-center justify-between rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <span class="text-muted-soft text-sm">Files after save</span>
                            <span class="font-display text-2xl text-white">{{ finalCount }}</span>
                        </div>
                        <div class="flex items-center justify-between rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <span class="text-muted-soft text-sm">Total after save</span>
                            <span class="font-display text-2xl text-secondary">{{ formatFileSize(finalSize) }}</span>
                        </div>
                    </div>

                    <div class="text-muted-soft mt-5 space-y-2 text-sm leading-6">
                        <p v-if="!withinCount">This update exceeds the {{ props.constraints.maxFiles }} file limit.</p>
                        <p v-if="!withinTotal">This update would exceed the 50 MB total limit.</p>
                        <p v-if="!hasSource">Keep a source link or at least one file on the request.</p>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="space-y-3">
                        <button
                            v-if="props.can.update"
                            type="button"
                            :disabled="!canSave"
                            class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                            @click="submit"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            Save changes
                        </button>

                        <button
                            v-if="props.can.delete"
                            type="button"
                            class="pill-button w-full justify-center bg-rose-500/12 text-rose-300"
                            @click="cancelRequest"
                        >
                            <Trash2 class="h-4 w-4" />
                            Cancel request
                        </button>
                    </div>

                    <p class="text-muted-soft mt-4 text-sm leading-6">
                        Pending requests stay editable. Once production moves forward, only admins can change the request.
                    </p>
                </article>
            </aside>
        </div>
    </LuminousAppLayout>
</template>
