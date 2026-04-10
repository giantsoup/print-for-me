<script setup lang="ts">
import PrintRequestStateActions from '@/components/luminous/PrintRequestStateActions.vue';
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import {
    formatDateOnly,
    formatDateTime,
    formatFileSize,
    isoDateToMaskedDisplay,
    maskedDateToIso,
    maskDateInput,
    stripNonDigits,
    type PrintRequestActionKey,
} from '@/lib/prints';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ChevronDown, ChevronUp, Download, ExternalLink, LoaderCircle, Mail, RefreshCcw, SquarePen, Trash2, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

interface FileItem {
    id: number;
    original_name: string;
    size_bytes: number;
}

interface SourcePreview {
    url?: string;
    domain?: string;
    site_name?: string;
    title?: string;
    description?: string;
    image_url?: string;
}

interface TimelineItem {
    key: string;
    label: string;
    description: string;
    at: string;
}

interface CompletionPhoto {
    id: number;
    original_name: string;
    mime_type?: string | null;
    size_bytes: number;
    width?: number | null;
    height?: number | null;
}

interface Props {
    printRequest: {
        id: number;
        status: string;
        source_url?: string | null;
        source_preview?: SourcePreview | null;
        source_preview_fetched_at?: string | null;
        source_preview_failed_at?: string | null;
        instructions?: string | null;
        needed_by_date?: string | null;
        files: FileItem[];
        created_at: string;
        deleted_at?: string | null;
        user?: {
            name: string;
            email: string;
        } | null;
    };
    completionPhotos: CompletionPhoto[];
    sourcePreviewPolicy?: 'allow' | 'block' | null;
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
    completionPhotoConstraints: {
        maxFiles: number;
    };
}

const props = defineProps<Props>();
const page = usePage();
const isEditing = ref(false);
const isSourceDescriptionExpanded = ref(false);
const isRefetchingSourcePreview = ref(false);
const isResendingCompletedNotice = ref(false);
const isSendingCompletedNoticePreview = ref(false);
const pickedFiles = ref<File[]>([]);

const form = useForm<{ source_url: string | null; needed_by_date: string; instructions: string | null; files: File[]; remove_file_ids: number[] }>({
    source_url: props.printRequest.source_url || '',
    needed_by_date: isoDateToMaskedDisplay(props.printRequest.needed_by_date),
    instructions: props.printRequest.instructions || '',
    files: [],
    remove_file_ids: [],
});

const flashStatus = computed(() => page.props.flash?.status);
const sourcePreview = computed(() => props.printRequest.source_preview || null);
const sourceUrl = computed(() => props.printRequest.source_url || null);
const sourceDomain = computed(() => sourcePreview.value?.domain || deriveSourceDomain(sourceUrl.value));
const sourceLabel = computed(() => sourcePreview.value?.site_name || sourceDomain.value || 'Source Link');
const sourceTitle = computed(() => sourcePreview.value?.title || sourcePreview.value?.site_name || sourceDomain.value || 'Open source');
const sourceDescription = computed(() => sourcePreview.value?.description || null);
const sourceDescriptionElement = ref<HTMLElement | null>(null);
const sourceDescriptionCanExpand = ref(false);
const sourceImageUrl = computed(() => sourcePreview.value?.image_url || null);
const sourcePreviewBlocked = computed(() => Boolean(sourceUrl.value) && props.sourcePreviewPolicy === 'block' && !sourcePreview.value);
const sourcePreviewPending = computed(
    () => Boolean(sourceUrl.value) && !sourcePreviewBlocked.value && !sourcePreview.value && !props.printRequest.source_preview_failed_at,
);
const sourcePreviewFailed = computed(
    () => Boolean(sourceUrl.value) && !sourcePreviewBlocked.value && !sourcePreview.value && Boolean(props.printRequest.source_preview_failed_at),
);
const pageTitle = computed(() => sourcePreview.value?.title || sourcePreview.value?.site_name || sourceDomain.value || 'Print request');
const completionPhotoCount = computed(() => props.completionPhotos.length);
const canResendCompletedNotice = computed(
    () => props.can.isAdmin && props.printRequest.status === 'complete' && Boolean(props.printRequest.user?.email),
);
const canSendCompletedNoticePreview = computed(() => props.can.isAdmin && props.printRequest.status === 'complete');

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
const canEditNeededByDate = computed(() => props.printRequest.status === 'pending');
const neededByDateDigits = computed(() => stripNonDigits(form.needed_by_date).length);
const neededByDateIso = computed(() => maskedDateToIso(form.needed_by_date.trim()));
const neededByDateClientError = computed(() => {
    if (!canEditNeededByDate.value || form.needed_by_date.trim() === '') {
        return null;
    }

    if (neededByDateDigits.value < 8) {
        return 'Finish the needed-by date as MM/DD/YYYY.';
    }

    if (!neededByDateIso.value) {
        return 'Enter a real calendar date as MM/DD/YYYY.';
    }

    return null;
});
const canSave = computed(
    () => isEditing.value && props.can.update && withinCount.value && withinTotal.value && hasSource.value && !neededByDateClientError.value && !form.processing,
);
const fileSignature = computed(() => props.printRequest.files.map((file) => `${file.id}:${file.original_name}`).join('|'));
let sourceDescriptionResizeObserver: ResizeObserver | null = null;

watch(
    () => [
        props.printRequest.source_url,
        props.printRequest.needed_by_date,
        props.printRequest.instructions,
        fileSignature.value,
        props.printRequest.source_preview_fetched_at,
        props.printRequest.source_preview_failed_at,
    ],
    () => {
        resetFormState();
        isEditing.value = false;
        isSourceDescriptionExpanded.value = false;
        sourceDescriptionCanExpand.value = false;
    },
);

watch(
    () => [sourceDescription.value, isSourceDescriptionExpanded.value],
    () => {
        syncSourceDescriptionOverflow();
    },
);

onMounted(() => {
    if (typeof ResizeObserver !== 'undefined') {
        sourceDescriptionResizeObserver = new ResizeObserver(() => {
            syncSourceDescriptionOverflow();
        });

        if (sourceDescriptionElement.value) {
            sourceDescriptionResizeObserver.observe(sourceDescriptionElement.value);
        }
    }

    syncSourceDescriptionOverflow();
});

onBeforeUnmount(() => {
    sourceDescriptionResizeObserver?.disconnect();
});

function onPickFiles(event: Event) {
    const input = event.target as HTMLInputElement;
    const list = input.files ? Array.from(input.files) : [];
    pickedFiles.value = list;
    form.files = list;
}

function onNeededByDateInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const maskedValue = maskDateInput(input.value);

    form.needed_by_date = maskedValue;
    input.value = maskedValue;
    form.clearErrors('needed_by_date');
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

function enterEditMode() {
    if (!props.can.update) {
        return;
    }

    resetFormState();
    isEditing.value = true;
}

function cancelEdit() {
    if (form.processing) {
        return;
    }

    resetFormState();
    isEditing.value = false;
}

function submit() {
    if (!canSave.value) {
        return;
    }

    form.patch(route('print-requests.update', { print_request: props.printRequest.id }), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            isEditing.value = false;
        },
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

function refetchSourcePreview() {
    if (!props.can.isAdmin || !sourceUrl.value || isRefetchingSourcePreview.value) {
        return;
    }

    isRefetchingSourcePreview.value = true;

    router.post(
        route('admin.print-requests.source-preview.refetch', { print_request: props.printRequest.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRefetchingSourcePreview.value = false;
            },
        },
    );
}

function resendCompletedNotice() {
    if (!canResendCompletedNotice.value || isResendingCompletedNotice.value) {
        return;
    }

    isResendingCompletedNotice.value = true;

    router.post(
        route('admin.print-requests.notifications.completed.resend', { print_request: props.printRequest.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isResendingCompletedNotice.value = false;
            },
        },
    );
}

function sendCompletedNoticePreview() {
    if (!canSendCompletedNoticePreview.value || isSendingCompletedNoticePreview.value) {
        return;
    }

    isSendingCompletedNoticePreview.value = true;

    router.post(
        route('admin.print-requests.notifications.completed.send-test', { print_request: props.printRequest.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isSendingCompletedNoticePreview.value = false;
            },
        },
    );
}

function resetFormState() {
    const defaults = {
        source_url: props.printRequest.source_url || '',
        needed_by_date: isoDateToMaskedDisplay(props.printRequest.needed_by_date),
        instructions: props.printRequest.instructions || '',
        files: [],
        remove_file_ids: [],
    };

    form.defaults(defaults);
    form.reset();
    form.clearErrors();
    form.files = [];
    form.remove_file_ids = [];
    pickedFiles.value = [];
}

function toggleSourceDescription() {
    isSourceDescriptionExpanded.value = !isSourceDescriptionExpanded.value;
    syncSourceDescriptionOverflow();
}

function deriveSourceDomain(value?: string | null) {
    if (!value) {
        return null;
    }

    try {
        return new URL(value).hostname.replace(/^www\./, '');
    } catch {
        return null;
    }
}

function syncSourceDescriptionOverflow() {
    nextTick(() => {
        if (sourceDescriptionResizeObserver) {
            sourceDescriptionResizeObserver.disconnect();

            if (sourceDescriptionElement.value) {
                sourceDescriptionResizeObserver.observe(sourceDescriptionElement.value);
            }
        }

        const descriptionElement = sourceDescriptionElement.value;

        if (!descriptionElement || !sourceDescription.value) {
            sourceDescriptionCanExpand.value = false;

            return;
        }

        if (isSourceDescriptionExpanded.value) {
            sourceDescriptionCanExpand.value = true;

            return;
        }

        sourceDescriptionCanExpand.value = descriptionElement.scrollHeight > descriptionElement.clientHeight + 1;
    });
}
</script>

<template>
    <Head :title="pageTitle" />

    <LuminousAppLayout active-nav="requests" wide :show-dock="false">
        <template #pageActions>
            <Link :href="route('print-requests.index')" prefetch class="pill-button pill-button-secondary w-full sm:w-auto">
                <ArrowLeft class="h-4 w-4" />
                {{ props.can.isAdmin ? 'Back to requests' : 'Back to my requests' }}
            </Link>
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.12fr_0.88fr]">
            <section class="space-y-6">
                <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                    <div class="flex flex-col gap-4">
                        <div class="min-w-0">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">For</p>
                            <p class="mt-2 text-base font-semibold tracking-tight text-white sm:text-lg">
                                {{ props.printRequest.user?.name || 'Unknown' }}
                            </p>
                            <p class="mt-1 text-sm break-all text-white/58">
                                {{ props.printRequest.user?.email || 'No email available' }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Status</p>
                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                    <StatusBadge :status="props.printRequest.status" />
                                    <p class="text-sm text-white/55">{{ existingCount }} {{ existingCount === 1 ? 'attachment' : 'attachments' }}</p>
                                </div>
                            </div>

                            <button
                                v-if="props.can.update && !isEditing"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center sm:w-auto"
                                @click="enterEditMode"
                            >
                                <SquarePen class="h-4 w-4" />
                                Edit
                            </button>

                            <button
                                v-if="isEditing"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center sm:w-auto"
                                :disabled="form.processing"
                                @click="cancelEdit"
                            >
                                <X class="h-4 w-4" />
                                Cancel editing
                            </button>
                        </div>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label v-if="isEditing" for="source_url" class="field-label">Request URL</label>
                            <p v-else class="field-label">Request URL</p>

                            <div v-if="isEditing" class="space-y-2">
                                <input id="source_url" v-model="form.source_url" type="url" placeholder="https://..." class="luminous-input" />
                                <p v-if="form.errors.source_url" class="text-sm text-rose-300">{{ form.errors.source_url }}</p>
                            </div>

                            <div v-else-if="sourceUrl" class="space-y-4">
                                <div v-if="props.can.isAdmin" class="flex justify-start">
                                    <button
                                        type="button"
                                        class="pill-button pill-button-secondary w-full justify-center sm:w-auto"
                                        :disabled="isRefetchingSourcePreview"
                                        @click="refetchSourcePreview"
                                    >
                                        <LoaderCircle v-if="isRefetchingSourcePreview" class="h-4 w-4 animate-spin" />
                                        <RefreshCcw v-else class="h-4 w-4" />
                                        Refetch request content
                                    </button>
                                </div>

                                <div class="overflow-hidden rounded-[1.45rem] border border-primary/16 bg-primary/[0.07] p-4 sm:p-5">
                                    <a
                                        v-if="sourceImageUrl && !sourcePreviewBlocked"
                                        :href="sourceUrl"
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        class="group block overflow-hidden rounded-[1.2rem] border border-white/8 bg-white/[0.03] transition-colors hover:border-primary/20 hover:bg-white/[0.05]"
                                    >
                                        <div class="relative h-32 w-full overflow-hidden sm:h-56">
                                            <img
                                                :src="sourceImageUrl"
                                                :alt="sourceTitle"
                                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                                            />
                                            <div
                                                class="absolute right-3 bottom-3 inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-white/10 bg-black/30 text-white/80 backdrop-blur-sm"
                                            >
                                                <ExternalLink class="h-4 w-4" />
                                            </div>
                                        </div>
                                    </a>

                                    <div class="mt-0 min-w-0" :class="sourceImageUrl && !sourcePreviewBlocked ? 'mt-4' : ''">
                                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                                            <span
                                                class="max-w-full rounded-full bg-white/[0.08] px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-primary uppercase"
                                            >
                                                {{ sourceLabel }}
                                            </span>
                                            <span
                                                v-if="sourcePreview && props.printRequest.source_preview_fetched_at"
                                                class="max-w-full rounded-full bg-white/[0.05] px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-white/55 uppercase"
                                            >
                                                Preview
                                            </span>
                                            <span
                                                v-if="sourcePreviewBlocked"
                                                class="max-w-full rounded-full bg-white/[0.05] px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-white/55 uppercase"
                                            >
                                                Preview blocked
                                            </span>
                                        </div>

                                        <a
                                            :href="sourceUrl"
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            class="mt-3 block max-w-3xl text-xl leading-tight font-semibold tracking-tight text-white transition-colors hover:text-primary sm:font-display sm:text-2xl"
                                        >
                                            {{ sourceTitle }}
                                        </a>
                                        <p v-if="sourcePreviewBlocked" class="mt-2 text-xs leading-5 text-white/46">
                                            Preview fetching is blocked for this website. Open the source page directly.
                                        </p>
                                        <p
                                            v-if="sourceDescription"
                                            ref="sourceDescriptionElement"
                                            class="text-muted-soft mt-2 max-w-3xl text-sm leading-6 break-words"
                                            :class="
                                                isSourceDescriptionExpanded
                                                    ? ''
                                                    : '[display:-webkit-box] overflow-hidden [-webkit-box-orient:vertical] [-webkit-line-clamp:4]'
                                            "
                                        >
                                            {{ sourceDescription }}
                                        </p>
                                        <button
                                            v-if="sourceDescriptionCanExpand"
                                            type="button"
                                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-primary"
                                            @click="toggleSourceDescription"
                                        >
                                            <component :is="isSourceDescriptionExpanded ? ChevronUp : ChevronDown" class="h-4 w-4" />
                                            {{ isSourceDescriptionExpanded ? 'View less' : 'View more' }}
                                        </button>
                                    </div>
                                </div>

                                <div v-if="sourcePreviewPending" class="rounded-[1.35rem] border border-white/6 bg-white/[0.03] px-4 py-4">
                                    <div class="animate-pulse space-y-3">
                                        <div class="h-3 w-24 rounded-full bg-white/10" />
                                        <div class="h-5 w-2/3 rounded-full bg-white/12" />
                                        <div class="h-3 w-full rounded-full bg-white/10" />
                                    </div>
                                    <p class="text-muted-soft mt-4 text-sm leading-6">Loading source preview.</p>
                                </div>

                                <p v-else-if="sourcePreviewFailed && !sourcePreviewBlocked" class="text-muted-soft text-sm leading-6">
                                    Preview unavailable. The source link is still available above.
                                </p>
                            </div>

                            <div
                                v-else
                                class="rounded-[1.35rem] border border-dashed border-white/10 bg-white/[0.02] px-4 py-4 text-sm text-white/55"
                            >
                                No request URL attached. Files below are the current source for this request.
                            </div>
                        </div>

                        <div>
                            <label v-if="isEditing && canEditNeededByDate" for="needed_by_date" class="field-label">Needed By</label>
                            <p v-else class="field-label">Needed By</p>

                            <div v-if="isEditing && canEditNeededByDate" class="space-y-2">
                                <input
                                    id="needed_by_date"
                                    :value="form.needed_by_date"
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="10"
                                    placeholder="MM/DD/YYYY"
                                    class="luminous-input"
                                    @input="onNeededByDateInput"
                                />
                                <p class="text-muted-soft text-sm leading-6">
                                    Optional. Use this when the request is connected to an event, deadline, or pickup date.
                                </p>
                                <p v-if="neededByDateClientError || form.errors.needed_by_date" class="text-sm text-rose-300">
                                    {{ neededByDateClientError || form.errors.needed_by_date }}
                                </p>
                            </div>

                            <div v-else class="rounded-[1.45rem] border border-white/6 bg-white/[0.035] px-4 py-4">
                                <p class="text-sm leading-7 text-white/85">
                                    {{ props.printRequest.needed_by_date ? formatDateOnly(props.printRequest.needed_by_date) : 'Flexible timing' }}
                                </p>
                                <p class="mt-2 text-sm leading-6 text-white/45">
                                    {{
                                        isEditing && !canEditNeededByDate
                                            ? 'Needed-by date is locked once the request leaves pending.'
                                            : 'Optional scheduling detail supplied by the requester.'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <label v-if="isEditing" for="instructions" class="field-label">Request Details</label>
                            <p v-else class="field-label">Request Details</p>

                            <div v-if="isEditing" class="space-y-2">
                                <textarea
                                    id="instructions"
                                    v-model="form.instructions"
                                    rows="6"
                                    class="luminous-textarea"
                                    placeholder="Describe the print, material, finish, color, tolerances, or anything else the queue should know."
                                />
                                <p v-if="form.errors.instructions" class="text-sm text-rose-300">{{ form.errors.instructions }}</p>
                            </div>

                            <div v-else class="rounded-[1.45rem] border border-white/6 bg-white/[0.035] px-4 py-4">
                                <p v-if="props.printRequest.instructions" class="text-sm leading-7 break-words whitespace-pre-line text-white/85">
                                    {{ props.printRequest.instructions }}
                                </p>
                                <p v-else class="text-sm leading-6 text-white/45">No instructions provided.</p>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0">
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Files</p>
                            <h2 class="mt-3 text-xl leading-tight font-semibold tracking-tight text-white sm:font-display sm:text-2xl">
                                Attachments
                            </h2>
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

                                <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                                    <a
                                        :href="route('print-requests.files.download', { print_request: props.printRequest.id, file: file.id })"
                                        class="pill-button pill-button-secondary w-full justify-center text-sm sm:w-auto"
                                    >
                                        <Download class="h-4 w-4" />
                                        Download
                                    </a>

                                    <label
                                        v-if="isEditing"
                                        class="inline-flex min-h-12 items-center gap-2 rounded-full border border-white/8 bg-white/[0.05] px-4 text-sm font-medium text-white/72"
                                    >
                                        <input
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-white/15 bg-transparent text-primary focus:ring-0"
                                            :checked="form.remove_file_ids.includes(file.id)"
                                            @change="(event: Event) => toggleRemove(file.id, (event.target as HTMLInputElement).checked)"
                                        />
                                        Remove
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="isEditing" class="mt-6">
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

                        <p v-if="form.errors.files" class="mt-3 text-sm text-rose-300">{{ form.errors.files }}</p>
                    </div>
                </article>

                <article v-if="completionPhotoCount" class="luminous-panel px-5 py-5">
                    <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                        <div class="min-w-0">
                            <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Finished Print</p>
                            <h2 class="mt-3 text-xl leading-tight font-semibold tracking-tight text-white sm:font-display sm:text-2xl">
                                Completion Photos
                            </h2>
                        </div>
                        <p class="text-muted-soft text-sm">{{ completionPhotoCount }} uploaded</p>
                    </div>

                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <a
                            v-for="(photo, index) in props.completionPhotos"
                            :key="photo.id"
                            :href="route('print-requests.completion-photos.show', { print_request: props.printRequest.id, photo: photo.id })"
                            target="_blank"
                            rel="noreferrer noopener"
                            class="group overflow-hidden rounded-[1.35rem] border border-white/8 bg-white/[0.04] transition-colors hover:border-primary/18 hover:bg-white/[0.06]"
                        >
                            <div class="aspect-[4/3] overflow-hidden bg-black/20">
                                <img
                                    :src="route('print-requests.completion-photos.show', { print_request: props.printRequest.id, photo: photo.id })"
                                    :alt="photo.original_name || `Completion photo ${index + 1}`"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.02]"
                                />
                            </div>

                            <div class="px-4 py-4">
                                <p class="font-medium text-white">Photo {{ index + 1 }}</p>
                                <p class="mt-1 text-xs tracking-[0.18em] text-white/42 uppercase">{{ formatFileSize(photo.size_bytes) }}</p>
                            </div>
                        </a>
                    </div>
                </article>
            </section>

            <aside class="space-y-6">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Request Info</p>
                    <div class="mt-6 grid gap-3">
                        <div class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/45 uppercase">Submitted</p>
                            <p class="mt-2 text-lg font-semibold tracking-tight text-white">{{ formatDateOnly(props.printRequest.created_at) }}</p>
                            <p class="mt-1 text-sm text-white/58">{{ formatDateTime(props.printRequest.created_at) }}</p>
                        </div>
                        <div class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/45 uppercase">Needed By</p>
                            <p class="mt-2 text-lg font-semibold tracking-tight text-white">
                                {{ props.printRequest.needed_by_date ? formatDateOnly(props.printRequest.needed_by_date) : 'Flexible timing' }}
                            </p>
                            <p class="mt-1 text-sm text-white/58">
                                {{ props.printRequest.needed_by_date ? 'Date-only scheduling note' : 'No deadline was added to this request.' }}
                            </p>
                        </div>
                    </div>
                </article>

                <article v-if="props.can.isAdmin" class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Workflow Control</p>
                    <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Workflow</h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">Update the request status.</p>

                    <div class="mt-6">
                        <PrintRequestStateActions
                            :request-id="props.printRequest.id"
                            :status="props.printRequest.status"
                            :actions="props.availableStatusActions"
                            :completion-photo-constraints="props.completionPhotoConstraints"
                        />
                    </div>

                    <div
                        v-if="canResendCompletedNotice || canSendCompletedNoticePreview"
                        class="mt-6 rounded-[1.35rem] border border-primary/12 bg-primary/[0.05] px-4 py-4"
                    >
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Delivery Check</p>
                        <p class="mt-2 text-sm leading-6 text-white/72">
                            Queue the completion notice again for the requester or send the same message to your own inbox to validate delivery after
                            an issue or deploy update.
                        </p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
                            <button
                                v-if="canResendCompletedNotice"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center"
                                :disabled="isResendingCompletedNotice"
                                @click="resendCompletedNotice"
                            >
                                <LoaderCircle v-if="isResendingCompletedNotice" class="h-4 w-4 animate-spin" />
                                <Mail v-else class="h-4 w-4" />
                                Resend to requester
                            </button>

                            <button
                                v-if="canSendCompletedNoticePreview"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center"
                                :disabled="isSendingCompletedNoticePreview"
                                @click="sendCompletedNoticePreview"
                            >
                                <LoaderCircle v-if="isSendingCompletedNoticePreview" class="h-4 w-4 animate-spin" />
                                <Mail v-else class="h-4 w-4" />
                                Send test to me
                            </button>
                        </div>
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

                <article v-if="isEditing" class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Edit Summary</p>
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
                        <p v-if="neededByDateClientError">{{ neededByDateClientError }}</p>
                    </div>
                </article>

                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">
                        {{ isEditing ? 'Save State' : 'Request State' }}
                    </p>
                    <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">
                        {{ isEditing ? 'Save changes.' : 'Read-only view.' }}
                    </h2>
                    <p class="text-muted-soft mt-3 text-sm leading-6">
                        {{
                            isEditing
                                ? 'Changes apply immediately after saving. Source previews refresh in the background when needed.'
                                : 'Select Edit to update this request.'
                        }}
                    </p>

                    <div class="mt-6 space-y-3">
                        <button
                            v-if="isEditing"
                            type="button"
                            :disabled="!canSave"
                            class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45"
                            @click="submit"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                            Save changes
                        </button>

                        <button
                            v-if="isEditing"
                            type="button"
                            class="pill-button pill-button-secondary w-full"
                            :disabled="form.processing"
                            @click="cancelEdit"
                        >
                            <X class="h-4 w-4" />
                            Cancel editing
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
                        Pending requests can be edited. Later changes are limited to admins, and needed-by dates lock once work leaves pending.
                    </p>
                </article>
            </aside>
        </div>
    </LuminousAppLayout>
</template>
