<script setup lang="ts">
import { statusLabel, type PrintRequestActionKey, type PrintRequestStatus } from '@/lib/prints';
import { router } from '@inertiajs/vue3';
import { Camera, CheckCheck, CircleDot, Images, LoaderCircle, RotateCcw, SquareCheckBig, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, type Component } from 'vue';

interface Props {
    requestId: number;
    status: PrintRequestStatus;
    actions: PrintRequestActionKey[];
    variant?: 'panel' | 'compact';
    completionPhotoConstraints?: {
        maxFiles: number;
    } | null;
}

interface ActionDefinition {
    key: PrintRequestActionKey;
    label: string;
    description: string;
    routeName: string;
    targetStatus: PrintRequestStatus;
    icon: Component;
    tone: string;
    confirmMessage?: string;
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'panel',
    completionPhotoConstraints: null,
});

const activeAction = ref<PrintRequestActionKey | null>(null);
const localError = ref<string | null>(null);
const uploadProgress = ref<number | null>(null);
const showCompletionUploader = ref(false);
const completionPhotos = ref<Array<{ file: File; previewUrl: string }>>([]);
const cameraInput = ref<HTMLInputElement | null>(null);
const libraryInput = ref<HTMLInputElement | null>(null);

const actionMap: Record<PrintRequestActionKey, ActionDefinition> = {
    accept: {
        key: 'accept',
        label: 'Accept request',
        description: 'Approve the request and move it into accepted so production can begin.',
        routeName: 'admin.print-requests.accept',
        targetStatus: 'accepted',
        icon: CheckCheck,
        tone: 'bg-secondary/12 text-secondary',
    },
    printing: {
        key: 'printing',
        label: 'Start printing',
        description: 'Move the request from accepted into active production.',
        routeName: 'admin.print-requests.printing',
        targetStatus: 'printing',
        icon: CircleDot,
        tone: 'bg-primary/12 text-primary',
    },
    complete: {
        key: 'complete',
        label: 'Mark complete',
        description: 'Close production and mark the request ready for pickup or delivery.',
        routeName: 'admin.print-requests.complete',
        targetStatus: 'complete',
        icon: SquareCheckBig,
        tone: 'bg-emerald-400/12 text-emerald-300',
    },
    revert: {
        key: 'revert',
        label: 'Return to pending',
        description: 'Send the request back to the review queue for another pass.',
        routeName: 'admin.print-requests.revert',
        targetStatus: 'pending',
        icon: RotateCcw,
        tone: 'bg-white/[0.06] text-white/76',
        confirmMessage: 'Return this request to pending?',
    },
};

const statusSteps = [
    { key: 'pending', label: 'Pending' },
    { key: 'accepted', label: 'Accepted' },
    { key: 'printing', label: 'Printing' },
    { key: 'complete', label: 'Complete' },
];

const currentStepIndex = computed(() => statusSteps.findIndex((step) => step.key === props.status));
const visibleActions = computed(() => props.actions.map((action) => actionMap[action]));
const isWorking = computed(() => activeAction.value !== null);
const shouldHideEmptyState = computed(() => props.variant === 'compact' && visibleActions.value.length === 0);
const canAttachCompletionPhotos = computed(
    () => props.variant === 'panel' && props.completionPhotoConstraints !== null && props.actions.includes('complete'),
);
const completionPhotoLimit = computed(() => props.completionPhotoConstraints?.maxFiles ?? 0);

function runAction(action: ActionDefinition) {
    if (isWorking.value) {
        return;
    }

    if (action.key === 'complete' && props.variant === 'compact') {
        router.visit(route('print-requests.show', { print_request: props.requestId }));

        return;
    }

    if (action.key !== 'complete') {
        hideCompletionUploader();
    }

    if (action.confirmMessage && !confirm(action.confirmMessage)) {
        return;
    }

    if (action.key === 'complete' && canAttachCompletionPhotos.value) {
        showCompletionUploader.value = true;
        localError.value = null;

        return;
    }

    activeAction.value = action.key;
    localError.value = null;

    router.patch(
        route(action.routeName, { print_request: props.requestId }),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                localError.value = errors.status ?? 'Unable to change the request state.';
            },
            onSuccess: () => {
                localError.value = null;
                hideCompletionUploader();
            },
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
}

function openPhotoPicker(type: 'camera' | 'library') {
    if (isWorking.value || !showCompletionUploader.value) {
        return;
    }

    if (type === 'camera') {
        cameraInput.value?.click();

        return;
    }

    libraryInput.value?.click();
}

function onPhotoInput(event: Event) {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];

    if (files.length === 0) {
        input.value = '';

        return;
    }

    const availableSlots = Math.max(0, completionPhotoLimit.value - completionPhotos.value.length);

    if (availableSlots === 0) {
        localError.value = `You can upload up to ${completionPhotoLimit.value} completion photos.`;
        input.value = '';

        return;
    }

    const additions = files.slice(0, availableSlots).map((file) => ({
        file,
        previewUrl: URL.createObjectURL(file),
    }));

    if (additions.length < files.length) {
        localError.value = `Only the first ${completionPhotoLimit.value} photos will be included.`;
    } else {
        localError.value = null;
    }

    completionPhotos.value = [...completionPhotos.value, ...additions];
    input.value = '';
}

function removeCompletionPhoto(index: number) {
    const selected = completionPhotos.value[index];

    if (!selected) {
        return;
    }

    URL.revokeObjectURL(selected.previewUrl);
    completionPhotos.value = completionPhotos.value.filter((_, photoIndex) => photoIndex !== index);
}

function submitCompletion(action: ActionDefinition) {
    if (isWorking.value) {
        return;
    }

    activeAction.value = action.key;
    localError.value = null;
    uploadProgress.value = null;

    router.post(
        route(action.routeName, { print_request: props.requestId }),
        {
            _method: 'patch',
            photos: completionPhotos.value.map((photo) => photo.file),
        },
        {
            forceFormData: true,
            preserveScroll: true,
            onProgress: (event) => {
                uploadProgress.value = event?.percentage ?? null;
            },
            onError: (errors) => {
                localError.value = errors.photos ?? errors['photos.0'] ?? errors.status ?? 'Unable to complete the request.';
            },
            onSuccess: () => {
                localError.value = null;
                hideCompletionUploader();
            },
            onFinish: () => {
                activeAction.value = null;
                uploadProgress.value = null;
            },
        },
    );
}

function hideCompletionUploader() {
    showCompletionUploader.value = false;
    releaseCompletionPhotoPreviews();
}

function releaseCompletionPhotoPreviews() {
    for (const photo of completionPhotos.value) {
        URL.revokeObjectURL(photo.previewUrl);
    }

    completionPhotos.value = [];
}

onBeforeUnmount(() => {
    releaseCompletionPhotoPreviews();
});
</script>

<template>
    <div v-if="!shouldHideEmptyState" class="space-y-4">
        <div v-if="props.variant === 'panel'" class="grid gap-2 sm:grid-cols-4">
            <div
                v-for="(step, index) in statusSteps"
                :key="step.key"
                class="rounded-[1.35rem] border px-4 py-4"
                :class="
                    index < currentStepIndex
                        ? 'border-primary/16 bg-primary/10'
                        : index === currentStepIndex
                          ? 'border-white/14 bg-white/[0.06]'
                          : 'border-white/8 bg-white/[0.03]'
                "
            >
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">{{ step.label }}</p>
                <p class="mt-3 text-sm font-semibold" :class="index <= currentStepIndex ? 'text-white' : 'text-white/45'">
                    {{ index < currentStepIndex ? 'Completed' : index === currentStepIndex ? 'Current state' : 'Upcoming' }}
                </p>
            </div>
        </div>

        <div
            v-if="visibleActions.length"
            :class="
                props.variant === 'panel'
                    ? 'grid gap-3'
                    : visibleActions.length > 1
                      ? 'grid gap-2 xl:grid-cols-2'
                      : 'grid gap-2'
            "
        >
            <button
                v-for="action in visibleActions"
                :key="action.key"
                type="button"
                :disabled="isWorking"
                class="w-full rounded-[1.35rem] border border-white/8 bg-white/[0.03] text-left transition-colors disabled:cursor-not-allowed disabled:opacity-55"
                :class="props.variant === 'panel' ? 'px-4 py-4 hover:bg-white/[0.06]' : 'px-4 py-3.5 hover:bg-white/[0.08]'"
                @click="runAction(action)"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="action.tone">
                        <component :is="action.icon" class="h-4 w-4" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-medium text-white">{{ action.label }}</p>
                        <p
                            class="mt-1 text-[0.68rem] font-semibold tracking-[0.18em] uppercase"
                            :class="props.variant === 'panel' ? 'text-white/42' : 'text-white/35'"
                        >
                            To {{ statusLabel(action.targetStatus) }}
                        </p>

                        <p v-if="props.variant === 'panel'" class="text-muted-soft mt-2 text-sm leading-6">
                            {{ action.description }}
                        </p>
                    </div>

                    <LoaderCircle v-if="activeAction === action.key" class="mt-0.5 h-4 w-4 shrink-0 animate-spin text-white/65" />
                </div>
            </button>
        </div>

        <div
            v-if="showCompletionUploader && canAttachCompletionPhotos"
            class="rounded-[1.35rem] border border-emerald-400/18 bg-emerald-400/8 px-4 py-4 sm:px-5"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-emerald-200/80 uppercase">Optional completion photos</p>
                    <p class="mt-2 text-sm leading-6 text-white/78">
                        Add a few final photos before closing the request. Images are resized for mobile viewing and full-size originals are not
                        retained.
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-full border border-white/10 px-4 text-sm font-medium text-white/72"
                    :disabled="isWorking"
                    @click="hideCompletionUploader"
                >
                    <X class="h-4 w-4" />
                    Close
                </button>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="flex min-h-14 items-center justify-center gap-2 rounded-[1.2rem] border border-white/10 bg-white/[0.05] px-4 text-sm font-medium text-white transition-colors hover:bg-white/[0.08] disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isWorking"
                    @click="openPhotoPicker('camera')"
                >
                    <Camera class="h-4 w-4" />
                    Take photo
                </button>

                <button
                    type="button"
                    class="flex min-h-14 items-center justify-center gap-2 rounded-[1.2rem] border border-white/10 bg-white/[0.05] px-4 text-sm font-medium text-white transition-colors hover:bg-white/[0.08] disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="isWorking"
                    @click="openPhotoPicker('library')"
                >
                    <Images class="h-4 w-4" />
                    Add from phone
                </button>
            </div>

            <input ref="cameraInput" type="file" accept="image/*" capture="environment" class="sr-only" @change="onPhotoInput" />
            <input ref="libraryInput" type="file" accept="image/*" multiple class="sr-only" @change="onPhotoInput" />

            <div v-if="completionPhotos.length" class="mt-5 grid gap-3 sm:grid-cols-2">
                <div
                    v-for="(photo, index) in completionPhotos"
                    :key="photo.previewUrl"
                    class="overflow-hidden rounded-[1.2rem] border border-white/10 bg-white/[0.04]"
                >
                    <div class="relative aspect-[4/3] overflow-hidden bg-black/20">
                        <img :src="photo.previewUrl" :alt="photo.file.name" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            class="absolute top-3 right-3 inline-flex h-9 w-9 items-center justify-center rounded-full border border-white/12 bg-black/45 text-white"
                            :disabled="isWorking"
                            @click="removeCompletionPhoto(index)"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <div class="px-4 py-3">
                        <p class="truncate text-sm font-medium text-white">{{ photo.file.name }}</p>
                        <p class="mt-1 text-xs tracking-[0.18em] text-white/42 uppercase">
                            {{ completionPhotos.length }} / {{ completionPhotoLimit }} selected
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs leading-5 text-white/50">
                    Up to {{ completionPhotoLimit }} photos. Camera capture works on phones and tablets that support it.
                </p>

                <button
                    type="button"
                    class="pill-button w-full justify-center bg-emerald-400/14 text-emerald-100 sm:w-auto"
                    :disabled="isWorking"
                    @click="submitCompletion(actionMap.complete)"
                >
                    <LoaderCircle v-if="activeAction === 'complete'" class="h-4 w-4 animate-spin" />
                    {{ completionPhotos.length ? 'Save photos and complete' : 'Complete without photos' }}
                </button>
            </div>

            <div v-if="uploadProgress !== null" class="mt-4 space-y-2">
                <div class="h-2 overflow-hidden rounded-full bg-white/10">
                    <div class="h-full rounded-full bg-emerald-300 transition-[width]" :style="{ width: `${uploadProgress}%` }" />
                </div>
                <p class="text-xs tracking-[0.16em] text-white/45 uppercase">Uploading {{ Math.round(uploadProgress) }}%</p>
            </div>
        </div>

        <div v-if="!visibleActions.length" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
            <p class="font-medium text-white">No further state changes are available.</p>
            <p class="text-muted-soft mt-2 text-sm leading-6">This request is currently {{ statusLabel(props.status).toLowerCase() }}.</p>
        </div>

        <p v-if="localError" class="text-sm text-rose-300">{{ localError }}</p>
    </div>
</template>
