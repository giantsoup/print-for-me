<script setup lang="ts">
import { formatRelative, neededByUrgency } from '@/lib/prints';
import { formatMinutes, type PlateActionKey, type QueueSlot } from '@/lib/queue';
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, Check, CircleX, LoaderCircle, Play, Undo2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{ kind: 'now' | 'next'; slot: QueueSlot | null; printerBusy: boolean }>();
const activeAction = ref<PlateActionKey | null>(null);
const errors = ref<Record<string, string>>({});
const actions = computed(() =>
    props.kind === 'now'
        ? ([
              { key: 'finish', label: 'Mark done', icon: Check, primary: true },
              { key: 'fail', label: 'Failed', icon: CircleX, primary: false },
              { key: 'requeue', label: 'Back to queue', icon: Undo2, primary: false },
          ] satisfies { key: PlateActionKey; label: string; icon: typeof Check; primary: boolean }[])
        : ([{ key: 'start', label: 'Start plate', icon: Play, primary: true }] satisfies {
              key: PlateActionKey;
              label: string;
              icon: typeof Check;
              primary: boolean;
          }[]),
);

function runAction(action: PlateActionKey): void {
    if (!props.slot || activeAction.value !== null) {
        return;
    }
    activeAction.value = action;
    errors.value = {};
    router.patch(
        route('admin.print-queue.plates.' + action, { print_queue_item: props.slot.item.id, plate: props.slot.plate.id }),
        {},
        {
            preserveScroll: true,
            onError: (responseErrors) => {
                errors.value = responseErrors;
            },
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
}
</script>

<template>
    <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">
            {{ props.kind === 'now' ? 'Now printing' : 'Up next' }}
        </p>
        <template v-if="props.slot">
            <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:mt-3 sm:font-display sm:text-2xl">{{ props.slot.plate.name }}</h2>
            <p class="text-muted-soft text-sm">{{ props.slot.item.title }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[0.68rem] tracking-[0.16em] text-white/42 uppercase">
                <span v-if="props.slot.item.for_whom">For {{ props.slot.item.for_whom }}</span>
                <span>{{ props.slot.plate.estimated_minutes === null ? 'Unestimated' : formatMinutes(props.slot.plate.estimated_minutes) }}</span>
                <span v-if="props.kind === 'now'">Started {{ formatRelative(props.slot.plate.started_at) }}</span>
            </div>
            <p class="mt-3 text-sm font-medium" :class="neededByUrgency(props.slot.item.needed_by_date).tone">
                {{ neededByUrgency(props.slot.item.needed_by_date).label }}
            </p>
            <div class="mt-4 grid gap-2 sm:flex sm:flex-wrap">
                <template v-if="props.kind === 'now' || !props.printerBusy">
                    <button
                        v-for="action in actions"
                        :key="action.key"
                        type="button"
                        class="pill-button w-full disabled:cursor-not-allowed disabled:opacity-45 sm:w-auto"
                        :class="action.primary ? 'pill-button-primary' : 'pill-button-secondary'"
                        :disabled="activeAction !== null"
                        @click="runAction(action.key)"
                    >
                        <LoaderCircle v-if="activeAction === action.key" class="h-4 w-4 animate-spin" />
                        <component :is="action.icon" v-else class="h-4 w-4" />
                        {{ action.label }}
                    </button>
                </template>
                <p v-else class="text-muted-soft text-sm">Finish the current plate first.</p>
                <Link :href="route('admin.print-queue.show', props.slot.item.id)" class="pill-button pill-button-secondary w-full justify-between"
                    >Open item <ArrowRight class="h-4 w-4"
                /></Link>
            </div>
            <p v-if="errors.status" role="alert" class="mt-3 text-sm text-rose-300">{{ errors.status }}</p>
        </template>
        <div v-else class="text-muted-soft mt-3 rounded-[1.35rem] bg-white/[0.04] px-4 py-6 text-sm">
            {{ props.kind === 'now' ? 'Printer is idle.' : 'Nothing queued. Add an item to fill the queue.' }}
        </div>
    </article>
</template>
