<script setup lang="ts">
import PrintQueueSlotCard from '@/components/luminous/PrintQueueSlotCard.vue';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateOnly, neededByUrgency } from '@/lib/prints';
import { formatMinutes, remainingLabel, type QueueItem, type QueueSlot, type QueueTotals } from '@/lib/queue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowDown, ArrowRight, ArrowUp, ArrowUpToLine, ChevronDown, ListOrdered, LoaderCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    nowPrinting: QueueSlot | null;
    upNext: QueueSlot | null;
    items: QueueItem[];
    doneItems: QueueItem[];
    doneCount: number;
    totals: QueueTotals;
}>();
const page = usePage();
const flashStatus = computed(() => page.props.flash?.status);
const isDoneOpen = ref(false);
type MoveDirection = 'up' | 'down' | 'top';
const pendingMove = ref<{ itemId: number; direction: MoveDirection } | null>(null);
const moveError = ref<{ itemId: number; message: string } | null>(null);
const moveActions = [
    { direction: 'up', label: 'Up', icon: ArrowUp },
    { direction: 'down', label: 'Down', icon: ArrowDown },
    { direction: 'top', label: 'Top', icon: ArrowUpToLine },
] as const;
const totals = computed(() => [
    { label: 'Items', value: props.totals.active_items },
    { label: 'Plates left', value: props.totals.remaining_plates },
    { label: 'Time left', value: formatMinutes(props.totals.remaining_minutes) },
]);

function isMoving(itemId: number, direction: MoveDirection): boolean {
    return pendingMove.value?.itemId === itemId && pendingMove.value.direction === direction;
}
function move(id: number, direction: MoveDirection): void {
    if (pendingMove.value !== null) return;
    pendingMove.value = { itemId: id, direction };
    moveError.value = null;
    router.patch(
        route('admin.print-queue.move', { print_queue_item: id }),
        { direction },
        {
            preserveScroll: true,
            onError: (responseErrors) => {
                moveError.value = { itemId: id, message: responseErrors.direction ?? responseErrors.item ?? responseErrors.status };
            },
            onFinish: () => {
                pendingMove.value = null;
            },
        },
    );
}
</script>

<template>
    <LuminousAppLayout
        active-nav="queue"
        eyebrow="Print Queue"
        title="Queue"
        intro="See what is printing, what to start next, and the order of everything waiting."
        wide
    >
        <Head title="Print Queue" />
        <template #pageActions>
            <Link :href="route('admin.print-queue.create')" class="pill-button pill-button-primary w-full sm:w-auto"
                >Add to queue <ArrowRight class="h-4 w-4"
            /></Link>
        </template>
        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>
        <section class="grid gap-3 lg:grid-cols-2">
            <PrintQueueSlotCard kind="now" :entry="props.nowPrinting" :printer-busy="props.nowPrinting !== null" />
            <PrintQueueSlotCard kind="next" :entry="props.upNext" :printer-busy="props.nowPrinting !== null" />
        </section>
        <section class="mt-6 grid grid-cols-3 gap-2 sm:gap-3">
            <article v-for="total in totals" :key="total.label" class="luminous-panel px-3 py-3 text-center sm:px-5 sm:py-4">
                <p class="text-[0.62rem] font-semibold tracking-[0.16em] text-white/42 uppercase">{{ total.label }}</p>
                <p class="mt-2 font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ total.value }}</p>
            </article>
        </section>
        <p v-if="props.totals.unestimated_plates" class="text-muted-soft mt-3 text-sm">+ {{ props.totals.unestimated_plates }} unestimated</p>
        <section class="mt-6 grid gap-3">
            <article v-for="(item, index) in props.items" :key="item.id" class="luminous-panel px-5 py-5">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(18rem,22rem)]">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-3">
                            <span
                                class="rounded-full bg-white/[0.06] px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-white/72 uppercase"
                                >#{{ item.position }}</span
                            >
                            <p class="text-xl font-semibold tracking-tight break-words text-white sm:font-display sm:text-2xl">{{ item.title }}</p>
                            <span
                                v-if="item.plate_counts.failed"
                                class="rounded-full bg-rose-400/12 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-rose-300 uppercase"
                                >Needs attention</span
                            >
                            <span
                                v-if="item.plate_counts.printing"
                                class="rounded-full bg-primary/12 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-primary uppercase"
                                >Printing</span
                            >
                        </div>
                        <p class="mt-4 text-sm font-medium" :class="neededByUrgency(item.needed_by_date).tone">
                            {{ neededByUrgency(item.needed_by_date).label }}
                        </p>
                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-[0.72rem] font-semibold tracking-[0.18em] text-white/42 uppercase"
                        >
                            <span v-if="item.for_whom">For {{ item.for_whom }}</span>
                            <span>{{ item.plate_counts.done }} of {{ item.plate_counts.total }} plates done</span>
                            <span>{{ remainingLabel(item.remaining_minutes, item.unestimated_plates) }}</span>
                            <Link
                                v-if="item.linked_request"
                                :href="route('print-requests.show', item.linked_request.id)"
                                class="inline-flex min-h-11 items-center"
                                >Request #{{ item.linked_request.id }}</Link
                            >
                        </div>
                    </div>
                    <div class="flex w-full flex-col gap-3 xl:self-start">
                        <Link :href="route('admin.print-queue.show', item.id)" class="pill-button pill-button-secondary w-full justify-between"
                            >Open item <ArrowRight class="h-4 w-4"
                        /></Link>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="action in moveActions"
                                :key="action.direction"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center px-3 disabled:cursor-not-allowed disabled:opacity-45"
                                :disabled="pendingMove !== null || (action.direction === 'down' ? index === props.items.length - 1 : index === 0)"
                                @click="move(item.id, action.direction)"
                            >
                                <LoaderCircle v-if="isMoving(item.id, action.direction)" class="h-4 w-4 animate-spin" />
                                <component :is="action.icon" v-else class="h-4 w-4" />{{ action.label }}
                            </button>
                        </div>
                        <p v-if="moveError?.itemId === item.id" role="alert" class="text-sm text-rose-300">{{ moveError.message }}</p>
                    </div>
                </div>
            </article>
            <div v-if="!props.items.length" class="luminous-panel px-5 py-10 text-center">
                <ListOrdered class="mx-auto h-5 w-5 text-primary" />
                <p class="mt-4 text-base font-medium text-white">The queue is empty.</p>
                <p class="text-muted-soft mt-2 text-sm">Add an item to plan what prints next.</p>
            </div>
        </section>
        <section class="luminous-panel mt-6 px-5 py-5">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 text-left"
                :aria-expanded="isDoneOpen"
                @click="isDoneOpen = !isDoneOpen"
            >
                <div class="space-y-1">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Done · {{ props.doneCount }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold tracking-[0.16em] text-white/60 uppercase"
                    >
                        {{ isDoneOpen ? 'Hide' : 'Show' }}
                    </span>
                    <ChevronDown class="h-4 w-4 text-white/55 transition-transform duration-200" :class="{ 'rotate-180': isDoneOpen }" />
                </div>
            </button>
            <div v-if="isDoneOpen" class="mt-4 grid gap-3">
                <article v-for="item in props.doneItems" :key="item.id" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                    <p class="font-medium break-words text-white">{{ item.title }}</p>
                    <p class="text-muted-soft mt-2 text-sm">{{ formatDateOnly(item.completed_at) }}</p>
                    <Link :href="route('admin.print-queue.show', item.id)" class="pill-button pill-button-secondary mt-3 w-full justify-between"
                        >Open item <ArrowRight class="h-4 w-4"
                    /></Link>
                    <Link
                        v-if="item.linked_request && item.linked_request.status !== 'complete' && !item.linked_request.is_deleted"
                        :href="route('print-requests.show', item.linked_request.id)"
                        class="pill-button pill-button-secondary mt-3 w-full justify-between"
                        >Request #{{ item.linked_request.id }} still needs completing</Link
                    >
                </article>
                <p v-if="props.doneCount > props.doneItems.length" class="text-muted-soft text-sm">
                    Showing the latest {{ props.doneItems.length }}.
                </p>
                <p v-if="!props.doneItems.length" class="text-muted-soft text-sm">No completed items yet.</p>
            </div>
        </section>
    </LuminousAppLayout>
</template>
