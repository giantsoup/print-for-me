<script setup lang="ts">
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { isoDateToMaskedDisplay, maskDateInput, maskedDateToIso, neededByUrgency, stripNonDigits } from '@/lib/prints';
import {
    formatMinutes,
    joinMinutes,
    plateStatusLabel,
    plateStatusTone,
    remainingLabel,
    splitMinutes,
    type LinkableRequest,
    type PlateActionKey,
    type QueueItem,
    type QueuePlate,
} from '@/lib/queue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    Check,
    CircleX,
    LoaderCircle,
    Play,
    Plus,
    RotateCcw,
    SquareCheckBig,
    SquarePen,
    Trash2,
    Undo2,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    item: QueueItem;
    printerBusy: boolean;
    printingPlate: { id: number; item_id: number; item_title: string; name: string } | null;
    linkableRequests: LinkableRequest[];
}>();
const page = usePage();
const flashStatus = computed(() => page.props.flash?.status);
const plates = computed(() => props.item.plates ?? []);
type PlateOperation = PlateActionKey | 'up' | 'down' | 'destroy';
type ItemOperation = 'complete' | 'reopen' | 'destroy';
const pendingPlateAction = ref<{ plateId: number; action: PlateOperation } | null>(null);
const plateActionError = ref<{ plateId: number; action: PlateOperation; message: string } | null>(null);
const pendingItemAction = ref<ItemOperation | null>(null);
const itemActionError = ref<{ action: ItemOperation; message: string } | null>(null);
const isDeleteOpen = ref(false);
const editingPlateId = ref<number | null>(null);
const actionMap = {
    start: { label: 'Start', icon: Play, primary: true },
    finish: { label: 'Done', icon: Check, primary: true },
    fail: { label: 'Failed', icon: CircleX, primary: false },
    requeue: { label: 'Back to queue', icon: Undo2, primary: false },
    retry: { label: 'Retry', icon: RotateCcw, primary: false },
    reopen: { label: 'Not done', icon: RotateCcw, primary: false },
};
function itemFields(item: QueueItem) {
    return {
        title: item.title,
        print_request_id: item.linked_request?.id ?? null,
        for_whom: item.for_whom ?? '',
        needed_by_date: isoDateToMaskedDisplay(item.needed_by_date),
        filament: item.filament ?? '',
        notes: item.notes ?? '',
    };
}
const form = useForm(itemFields(props.item));
const plateForm = useForm({ name: '', hours: '', minutes: '', filament: '', notes: '' });
const addForm = useForm({ name: 'Plate ' + (plates.value.length + 1), hours: '', minutes: '', filament: '' });
const plateErrors = computed<Record<string, string | undefined>>(() => plateForm.errors);
const addErrors = computed<Record<string, string | undefined>>(() => addForm.errors);
const dateError = computed(() => {
    if (!form.needed_by_date.trim()) return null;
    if (stripNonDigits(form.needed_by_date).length < 8) return 'Finish the needed-by date as MM/DD/YYYY.';
    if (!maskedDateToIso(form.needed_by_date)) return 'Enter a real calendar date as MM/DD/YYYY.';
    return null;
});
watch(
    () => props.item,
    (item) => {
        if (!form.isDirty) {
            form.defaults(itemFields(item));
            form.reset();
        }
    },
);
watch(
    () => form.print_request_id,
    (id) => {
        if (!form.for_whom.trim()) form.for_whom = props.linkableRequests.find((request) => request.id === id)?.user_name ?? '';
    },
);
function onDateInput(event: Event): void {
    if (event.target instanceof HTMLInputElement) {
        form.needed_by_date = maskDateInput(event.target.value);
        event.target.value = form.needed_by_date;
        form.clearErrors('needed_by_date');
    }
}
function saveItem(): void {
    if (dateError.value || form.processing || !form.isDirty) return;
    form.transform((data) => ({ ...data, needed_by_date: maskedDateToIso(data.needed_by_date.trim()) })).patch(
        route('admin.print-queue.update', props.item.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults(itemFields(props.item));
                form.reset();
            },
        },
    );
}
function isPlatePending(plateId: number, action: PlateOperation): boolean {
    return pendingPlateAction.value?.plateId === plateId && pendingPlateAction.value.action === action;
}
function runPlate(plate: QueuePlate, action: PlateOperation): void {
    if (pendingPlateAction.value !== null) return;
    if (action === 'destroy' && !confirm('Delete ' + plate.name + '?')) return;
    pendingPlateAction.value = { plateId: plate.id, action };
    plateActionError.value = null;
    const isMove = action === 'up' || action === 'down';
    const url = route('admin.print-queue.plates.' + (isMove ? 'move' : action), { print_queue_item: props.item.id, plate: plate.id });
    const options = {
        preserveScroll: true,
        onError: (responseErrors: Record<string, string>) => {
            plateActionError.value = {
                plateId: plate.id,
                action,
                message: responseErrors.status ?? responseErrors.plate ?? responseErrors.direction,
            };
        },
        onFinish: () => {
            pendingPlateAction.value = null;
        },
    };
    if (action === 'destroy') router.delete(url, options);
    else router.patch(url, isMove ? { direction: action } : {}, options);
}
function beginEdit(plate: QueuePlate): void {
    plateForm.defaults({ name: plate.name, ...splitMinutes(plate.estimated_minutes), filament: plate.filament ?? '', notes: plate.notes ?? '' });
    plateForm.reset();
    plateForm.clearErrors();
    editingPlateId.value = plate.id;
}
function savePlate(): void {
    if (editingPlateId.value === null || plateForm.processing || !plateForm.isDirty) return;
    plateForm
        .transform((data) => ({
            name: data.name,
            estimated_minutes: joinMinutes(data.hours, data.minutes),
            filament: data.filament,
            notes: data.notes,
        }))
        .patch(route('admin.print-queue.plates.update', { print_queue_item: props.item.id, plate: editingPlateId.value }), {
            preserveScroll: true,
            onSuccess: () => {
                editingPlateId.value = null;
            },
        });
}
function addPlate(): void {
    addForm
        .transform((data) => ({ name: data.name, estimated_minutes: joinMinutes(data.hours, data.minutes), filament: data.filament }))
        .post(route('admin.print-queue.plates.store', props.item.id), {
            preserveScroll: true,
            onSuccess: () => {
                addForm.defaults({ name: 'Plate ' + (plates.value.length + 1), hours: '', minutes: '', filament: '' });
                addForm.reset();
            },
        });
}
function runItem(action: ItemOperation): void {
    if (pendingItemAction.value !== null) return;
    if (action === 'complete' && props.item.plate_counts.queued > 0 && !confirm('Mark this item done?')) return;
    pendingItemAction.value = action;
    itemActionError.value = null;
    const options = {
        preserveScroll: true,
        onError: (responseErrors: Record<string, string>) => {
            itemActionError.value = { action, message: responseErrors.item ?? responseErrors.plate ?? responseErrors.status };
        },
        onFinish: () => {
            pendingItemAction.value = null;
        },
    };
    const url = route('admin.print-queue.' + action, props.item.id);
    if (action === 'destroy') router.delete(url, options);
    else router.patch(url, {}, options);
}
</script>

<template>
    <LuminousAppLayout
        active-nav="queue"
        eyebrow="Queue item"
        :title="props.item.title"
        :intro="props.item.for_whom ? 'For ' + props.item.for_whom : ''"
        wide
    >
        <Head :title="props.item.title" />
        <template #pageActions
            ><Link :href="route('admin.print-queue.index')" class="pill-button pill-button-secondary w-full sm:w-auto"
                ><ArrowLeft class="h-4 w-4" />Queue</Link
            ></template
        >
        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>
        <section class="grid gap-3 xl:grid-cols-[1.25fr_0.75fr]">
            <article class="luminous-panel min-w-0 px-5 py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Plates</p>
                <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">
                    {{ props.item.plate_counts.done }} of {{ props.item.plate_counts.total }} done
                </h2>
                <p class="text-muted-soft text-sm">{{ remainingLabel(props.item.remaining_minutes, props.item.unestimated_plates) }}</p>
                <div
                    v-if="props.printerBusy && props.printingPlate && props.printingPlate.item_id !== props.item.id"
                    class="mt-4 rounded-[1.35rem] border border-primary/12 bg-primary/[0.05] px-4 py-4 text-sm text-primary"
                >
                    Printer busy with {{ props.printingPlate.name }} from {{ props.printingPlate.item_title }}
                </div>
                <div
                    v-if="
                        props.item.completed_at &&
                        props.item.linked_request &&
                        props.item.linked_request.status !== 'complete' &&
                        !props.item.linked_request.is_deleted
                    "
                    class="mt-4 rounded-[1.35rem] border border-primary/12 bg-primary/[0.05] px-4 py-4 text-sm text-primary"
                >
                    <p>Mark the request complete to notify {{ props.item.linked_request.user_name || 'the requester' }}.</p>
                    <Link :href="route('print-requests.show', props.item.linked_request.id)" class="pill-button pill-button-secondary mt-3 w-full"
                        >Open request</Link
                    >
                </div>
                <div class="mt-5 grid gap-3">
                    <article v-for="(plate, index) in plates" :key="plate.id" class="min-w-0 rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                        <div class="flex flex-col gap-3">
                            <div class="min-w-0">
                                <StatusBadge :status="plate.status" :tone="plateStatusTone(plate.status)" :label="plateStatusLabel(plate.status)" />
                                <p class="mt-3 font-medium break-words text-white">{{ plate.name }}</p>
                                <p class="mt-1 text-xs tracking-[0.18em] text-white/42 uppercase">
                                    {{ plate.estimated_minutes === null ? 'Unestimated' : formatMinutes(plate.estimated_minutes)
                                    }}<span v-if="plate.filament"> · {{ plate.filament }}</span>
                                </p>
                                <p v-if="plate.notes" class="text-muted-soft mt-2 text-sm leading-6 whitespace-pre-line">{{ plate.notes }}</p>
                            </div>
                            <div class="flex w-full flex-col gap-2">
                                <button
                                    v-for="action in plate.availableActions"
                                    :key="action"
                                    type="button"
                                    class="pill-button w-full justify-center disabled:cursor-not-allowed disabled:opacity-45"
                                    :class="actionMap[action].primary ? 'pill-button-primary' : 'pill-button-secondary'"
                                    :disabled="pendingPlateAction !== null"
                                    @click="runPlate(plate, action)"
                                >
                                    <LoaderCircle v-if="isPlatePending(plate.id, action)" class="h-4 w-4 animate-spin" /><component
                                        :is="actionMap[action].icon"
                                        v-else
                                        class="h-4 w-4"
                                    />{{ actionMap[action].label }}
                                </button>
                                <div class="grid grid-cols-4 gap-2">
                                    <button
                                        type="button"
                                        class="pill-button pill-button-secondary min-w-0 flex-col px-3 disabled:cursor-not-allowed disabled:opacity-45 sm:flex-row"
                                        :disabled="plateForm.processing"
                                        @click="beginEdit(plate)"
                                    >
                                        <SquarePen class="h-4 w-4" />Edit
                                    </button>
                                    <button
                                        type="button"
                                        class="pill-button pill-button-secondary min-w-0 flex-col px-3 disabled:cursor-not-allowed disabled:opacity-45 sm:flex-row"
                                        :disabled="index === 0 || pendingPlateAction !== null"
                                        @click="runPlate(plate, 'up')"
                                    >
                                        <LoaderCircle v-if="isPlatePending(plate.id, 'up')" class="h-4 w-4 animate-spin" />
                                        <ArrowUp v-else class="h-4 w-4" />Up
                                    </button>
                                    <button
                                        type="button"
                                        class="pill-button pill-button-secondary min-w-0 flex-col px-3 disabled:cursor-not-allowed disabled:opacity-45 sm:flex-row"
                                        :disabled="index === plates.length - 1 || pendingPlateAction !== null"
                                        @click="runPlate(plate, 'down')"
                                    >
                                        <LoaderCircle v-if="isPlatePending(plate.id, 'down')" class="h-4 w-4 animate-spin" />
                                        <ArrowDown v-else class="h-4 w-4" />Down
                                    </button>
                                    <button
                                        type="button"
                                        class="pill-button pill-button-secondary min-w-0 flex-col px-3 disabled:cursor-not-allowed disabled:opacity-45 sm:flex-row"
                                        :disabled="pendingPlateAction !== null"
                                        @click="runPlate(plate, 'destroy')"
                                    >
                                        <LoaderCircle v-if="isPlatePending(plate.id, 'destroy')" class="h-4 w-4 animate-spin" />
                                        <Trash2 v-else class="h-4 w-4" />Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-if="plateActionError?.plateId === plate.id" role="alert" class="mt-3 text-sm text-rose-300">
                            {{ plateActionError.message }}
                        </p>
                        <form v-if="editingPlateId === plate.id" class="mt-5 grid gap-4" @submit.prevent="savePlate">
                            <label class="grid gap-2"
                                ><span class="field-label">Name</span
                                ><input v-model="plateForm.name" class="luminous-input" maxlength="120" required /><span
                                    v-if="plateForm.errors.name"
                                    class="text-sm text-rose-300"
                                    >{{ plateForm.errors.name }}</span
                                ></label
                            >
                            <div class="grid grid-cols-2 gap-3">
                                <label class="grid gap-2"
                                    ><span class="field-label">Hours</span
                                    ><input
                                        v-model="plateForm.hours"
                                        type="text"
                                        inputmode="numeric"
                                        pattern="[0-9]*"
                                        class="luminous-input" /></label
                                ><label class="grid gap-2"
                                    ><span class="field-label">Min</span
                                    ><input v-model="plateForm.minutes" type="text" inputmode="numeric" pattern="[0-9]*" class="luminous-input"
                                /></label>
                            </div>
                            <p v-if="plateErrors.estimated_minutes" class="text-sm text-rose-300">{{ plateErrors.estimated_minutes }}</p>
                            <label class="grid gap-2"
                                ><span class="field-label">Filament</span
                                ><input v-model="plateForm.filament" class="luminous-input" maxlength="160" /><span
                                    v-if="plateForm.errors.filament"
                                    class="text-sm text-rose-300"
                                    >{{ plateForm.errors.filament }}</span
                                ></label
                            >
                            <label class="grid gap-2"
                                ><span class="field-label">Notes</span
                                ><textarea v-model="plateForm.notes" class="luminous-textarea" maxlength="2000" /><span
                                    v-if="plateForm.errors.notes"
                                    class="text-sm text-rose-300"
                                    >{{ plateForm.errors.notes }}</span
                                ></label
                            >
                            <div class="grid gap-2 sm:flex">
                                <button
                                    type="submit"
                                    class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto"
                                    :disabled="plateForm.processing || !plateForm.isDirty"
                                >
                                    <LoaderCircle v-if="plateForm.processing" class="h-4 w-4 animate-spin" />Save plate</button
                                ><button
                                    type="button"
                                    class="pill-button pill-button-secondary w-full disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto"
                                    :disabled="plateForm.processing"
                                    @click="editingPlateId = null"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </article>
                </div>
                <form v-if="!props.item.completed_at" class="mt-5 grid gap-4" @submit.prevent="addPlate">
                    <label class="grid gap-2"
                        ><span class="field-label">Name</span><input v-model="addForm.name" class="luminous-input" maxlength="120" required /><span
                            v-if="addForm.errors.name"
                            class="text-sm text-rose-300"
                            >{{ addForm.errors.name }}</span
                        ></label
                    >
                    <div class="grid grid-cols-2 gap-3">
                        <label class="grid gap-2"
                            ><span class="field-label">Hours</span
                            ><input v-model="addForm.hours" type="text" inputmode="numeric" pattern="[0-9]*" class="luminous-input" /></label
                        ><label class="grid gap-2"
                            ><span class="field-label">Min</span
                            ><input v-model="addForm.minutes" type="text" inputmode="numeric" pattern="[0-9]*" class="luminous-input"
                        /></label>
                    </div>
                    <p v-if="addErrors.estimated_minutes" class="text-sm text-rose-300">{{ addErrors.estimated_minutes }}</p>
                    <label class="grid gap-2"
                        ><span class="field-label">Filament</span><input v-model="addForm.filament" class="luminous-input" maxlength="160" /><span
                            v-if="addForm.errors.filament"
                            class="text-sm text-rose-300"
                            >{{ addForm.errors.filament }}</span
                        ></label
                    >
                    <p v-if="addErrors.plate" class="text-sm text-rose-300">{{ addErrors.plate }}</p>
                    <button
                        type="submit"
                        class="pill-button pill-button-secondary w-full disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto"
                        :disabled="addForm.processing || !addForm.name.trim()"
                    >
                        <LoaderCircle v-if="addForm.processing" class="h-4 w-4 animate-spin" /><Plus v-else class="h-4 w-4" />Add plate
                    </button>
                </form>
                <p v-else class="text-muted-soft mt-5 text-sm">Reopen this item to add plates.</p>
            </article>
            <aside class="min-w-0 space-y-3">
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Summary</p>
                    <div class="mt-5 grid gap-3">
                        <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Position</p>
                            <p class="mt-2 text-sm text-white">{{ props.item.position === null ? 'Done' : '#' + props.item.position }}</p>
                        </div>
                        <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Needed by</p>
                            <p class="mt-2 text-sm" :class="neededByUrgency(props.item.needed_by_date).tone">
                                {{ neededByUrgency(props.item.needed_by_date).label }}
                            </p>
                        </div>
                        <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Filament</p>
                            <p class="mt-2 text-sm break-words text-white">{{ props.item.filament || 'Not specified' }}</p>
                        </div>
                        <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Linked request</p>
                            <p class="mt-2 text-sm text-white">
                                <Link
                                    v-if="props.item.linked_request"
                                    :href="route('print-requests.show', props.item.linked_request.id)"
                                    class="inline-flex min-h-11 items-center"
                                    >Request #{{ props.item.linked_request.id }}</Link
                                ><span v-else>None</span
                                ><span
                                    v-if="props.item.linked_request?.is_deleted"
                                    class="ml-2 rounded-full bg-rose-400/12 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-rose-200 uppercase"
                                    >Deleted</span
                                >
                            </p>
                            <div
                                v-if="
                                    props.item.completed_at &&
                                    props.item.linked_request &&
                                    props.item.linked_request.status !== 'complete' &&
                                    !props.item.linked_request.is_deleted
                                "
                                class="mt-3 text-sm text-primary"
                            >
                                <p>Mark the request complete to notify {{ props.item.linked_request.user_name || 'the requester' }}.</p>
                                <Link
                                    :href="route('print-requests.show', props.item.linked_request.id)"
                                    class="pill-button pill-button-secondary mt-3 w-full justify-center"
                                    >Open request</Link
                                >
                            </div>
                        </div>
                    </div>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Details</p>
                    <form class="mt-5 grid gap-4" @submit.prevent="saveItem">
                        <label class="grid gap-2"
                            ><span class="field-label">Title</span><input v-model="form.title" class="luminous-input" maxlength="160" required /><span
                                v-if="form.errors.title"
                                class="text-sm text-rose-300"
                                >{{ form.errors.title }}</span
                            ></label
                        >
                        <label class="grid gap-2"
                            ><span class="field-label">Linked request</span
                            ><select v-model="form.print_request_id" class="luminous-input">
                                <option :value="null">No linked request</option>
                                <option
                                    v-if="
                                        props.item.linked_request &&
                                        !props.linkableRequests.some((request) => request.id === props.item.linked_request?.id)
                                    "
                                    :value="props.item.linked_request.id"
                                >
                                    Request #{{ props.item.linked_request.id }} · {{ props.item.linked_request.user_name }}
                                </option>
                                <option v-for="request in props.linkableRequests" :key="request.id" :value="request.id">
                                    {{ request.label }}
                                </option></select
                            ><span v-if="form.errors.print_request_id" class="text-sm text-rose-300">{{ form.errors.print_request_id }}</span></label
                        >
                        <label class="grid gap-2"
                            ><span class="field-label">For whom</span><input v-model="form.for_whom" class="luminous-input" maxlength="120" /><span
                                v-if="form.errors.for_whom"
                                class="text-sm text-rose-300"
                                >{{ form.errors.for_whom }}</span
                            ></label
                        >
                        <label class="grid gap-2"
                            ><span class="field-label">Needed by</span
                            ><input
                                :value="form.needed_by_date"
                                type="text"
                                inputmode="numeric"
                                maxlength="10"
                                placeholder="MM/DD/YYYY"
                                class="luminous-input"
                                @input="onDateInput"
                            /><span v-if="dateError || form.errors.needed_by_date" class="text-sm text-rose-300">{{
                                dateError || form.errors.needed_by_date
                            }}</span></label
                        >
                        <label class="grid gap-2"
                            ><span class="field-label">Filament</span><input v-model="form.filament" class="luminous-input" maxlength="160" /><span
                                v-if="form.errors.filament"
                                class="text-sm text-rose-300"
                                >{{ form.errors.filament }}</span
                            ></label
                        >
                        <label class="grid gap-2"
                            ><span class="field-label">Notes</span><textarea v-model="form.notes" class="luminous-textarea" maxlength="5000" /><span
                                v-if="form.errors.notes"
                                class="text-sm text-rose-300"
                                >{{ form.errors.notes }}</span
                            ></label
                        >
                        <button
                            type="submit"
                            class="pill-button pill-button-primary w-full disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto"
                            :disabled="form.processing || !form.isDirty"
                        >
                            <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />Save changes
                        </button>
                    </form>
                </article>
                <article class="luminous-panel px-5 py-5">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Lifecycle</p>
                    <div class="mt-5 grid gap-3">
                        <button
                            v-if="!props.item.completed_at"
                            type="button"
                            class="pill-button pill-button-secondary w-full justify-between disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="props.item.plate_counts.printing > 0 || pendingItemAction !== null"
                            @click="runItem('complete')"
                        >
                            <span class="inline-flex items-center gap-2"><SquareCheckBig class="h-4 w-4" /> Mark item done</span>
                            <LoaderCircle v-if="pendingItemAction === 'complete'" class="h-4 w-4 animate-spin" />
                        </button>
                        <button
                            v-else
                            type="button"
                            class="pill-button pill-button-secondary w-full justify-between disabled:cursor-not-allowed disabled:opacity-45"
                            :disabled="pendingItemAction !== null"
                            @click="runItem('reopen')"
                        >
                            <span class="inline-flex items-center gap-2"><RotateCcw class="h-4 w-4" /> Reopen item</span>
                            <LoaderCircle v-if="pendingItemAction === 'reopen'" class="h-4 w-4 animate-spin" />
                        </button>
                        <p v-if="!props.item.completed_at && props.item.plate_counts.printing > 0" class="text-muted-soft text-sm">
                            Finish or requeue the printing plate first.
                        </p>
                        <p v-if="itemActionError && itemActionError.action !== 'destroy'" role="alert" class="text-sm text-rose-300">
                            {{ itemActionError.message }}
                        </p>
                        <Dialog v-model:open="isDeleteOpen">
                            <DialogTrigger as-child
                                ><button
                                    type="button"
                                    class="pill-button pill-button-secondary w-full justify-between disabled:cursor-not-allowed disabled:opacity-45"
                                    :disabled="pendingItemAction !== null"
                                >
                                    <span class="inline-flex items-center gap-2"><Trash2 class="h-4 w-4" /> Delete item</span>
                                </button></DialogTrigger
                            >
                            <DialogContent>
                                <DialogHeader
                                    ><DialogTitle>Delete this queue item?</DialogTitle
                                    ><DialogDescription>Its plates are removed too. A linked request is not changed.</DialogDescription></DialogHeader
                                >
                                <p v-if="itemActionError?.action === 'destroy'" role="alert" class="text-sm text-rose-300">
                                    {{ itemActionError.message }}
                                </p>
                                <form @submit.prevent="runItem('destroy')">
                                    <DialogFooter class="gap-2"
                                        ><DialogClose as-child
                                            ><button
                                                type="button"
                                                class="pill-button pill-button-secondary disabled:cursor-not-allowed disabled:opacity-45"
                                                :disabled="pendingItemAction !== null"
                                            >
                                                Cancel
                                            </button></DialogClose
                                        ><button
                                            type="submit"
                                            class="pill-button pill-button-primary disabled:cursor-not-allowed disabled:opacity-45"
                                            :disabled="pendingItemAction !== null"
                                        >
                                            <LoaderCircle v-if="pendingItemAction === 'destroy'" class="h-4 w-4 animate-spin" />Delete item
                                        </button></DialogFooter
                                    >
                                </form>
                            </DialogContent>
                        </Dialog>
                    </div>
                </article>
            </aside>
        </section>
    </LuminousAppLayout>
</template>
