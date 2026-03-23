<script setup lang="ts">
import { statusLabel, type PrintRequestActionKey, type PrintRequestStatus } from '@/lib/prints';
import { router } from '@inertiajs/vue3';
import { CheckCheck, CircleDot, LoaderCircle, RotateCcw, SquareCheckBig } from 'lucide-vue-next';
import { computed, ref, type Component } from 'vue';

interface Props {
    requestId: number;
    status: PrintRequestStatus;
    actions: PrintRequestActionKey[];
    variant?: 'panel' | 'compact';
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
});

const activeAction = ref<PrintRequestActionKey | null>(null);
const localError = ref<string | null>(null);

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

function runAction(action: ActionDefinition) {
    if (isWorking.value) {
        return;
    }

    if (action.confirmMessage && !confirm(action.confirmMessage)) {
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
            },
            onFinish: () => {
                activeAction.value = null;
            },
        },
    );
}
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

        <div v-if="visibleActions.length" :class="props.variant === 'panel' ? 'grid gap-3' : 'grid gap-2 sm:grid-cols-2'">
            <button
                v-for="action in visibleActions"
                :key="action.key"
                type="button"
                :disabled="isWorking"
                class="w-full rounded-[1.35rem] border border-white/8 text-left transition-colors disabled:cursor-not-allowed disabled:opacity-55"
                :class="props.variant === 'panel' ? 'px-4 py-4 hover:bg-white/[0.06]' : 'px-4 py-3 hover:bg-white/[0.05]'"
                @click="runAction(action)"
            >
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl" :class="action.tone">
                        <component :is="action.icon" class="h-4 w-4" />
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="font-medium text-white">{{ action.label }}</p>
                            <span
                                class="text-[0.68rem] font-semibold tracking-[0.18em] uppercase"
                                :class="props.variant === 'panel' ? 'text-white/42' : 'text-white/35'"
                            >
                                To {{ statusLabel(action.targetStatus) }}
                            </span>
                        </div>

                        <p v-if="props.variant === 'panel'" class="text-muted-soft mt-2 text-sm leading-6">
                            {{ action.description }}
                        </p>
                    </div>

                    <LoaderCircle v-if="activeAction === action.key" class="mt-0.5 h-4 w-4 animate-spin text-white/65" />
                </div>
            </button>
        </div>

        <div v-else class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
            <p class="font-medium text-white">No further state changes are available.</p>
            <p class="text-muted-soft mt-2 text-sm leading-6">This request is currently {{ statusLabel(props.status).toLowerCase() }}.</p>
        </div>

        <p v-if="localError" class="text-sm text-rose-300">{{ localError }}</p>
    </div>
</template>
