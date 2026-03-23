<script setup lang="ts">
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateTime, formatRelative } from '@/lib/prints';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Clock3, FolderOpen, Layers3, Sparkles, UserPlus2 } from 'lucide-vue-next';
import { computed } from 'vue';

interface DashboardRequest {
    id: number;
    user_id: number;
    status: string;
    source_url?: string | null;
    instructions?: string | null;
    created_at: string;
    accepted_at?: string | null;
    completed_at?: string | null;
    reverted_at?: string | null;
    files_count: number;
    user?: {
        name: string;
        email: string;
    } | null;
}

interface ActivityItem {
    id: string;
    kind: string;
    title: string;
    description: string;
    at: string;
    request_id: number;
}

interface Props {
    isAdmin: boolean;
    statusCounts: Record<string, number>;
    recentRequests: DashboardRequest[];
    recentActivity: ActivityItem[];
}

const props = defineProps<Props>();

const activeCount = computed(() => (props.statusCounts.accepted ?? 0) + (props.statusCounts.printing ?? 0));

const metricCards = computed(() => {
    if (props.isAdmin) {
        return [
            { label: 'Total requests', value: props.statusCounts.all ?? 0, tone: 'text-white' },
            { label: 'Active production', value: activeCount.value, tone: 'text-primary' },
            { label: 'Pending review', value: props.statusCounts.pending ?? 0, tone: 'text-secondary' },
            { label: 'Completed', value: props.statusCounts.complete ?? 0, tone: 'text-emerald-300' },
        ];
    }

    return [
        { label: 'Open requests', value: activeCount.value + (props.statusCounts.pending ?? 0), tone: 'text-primary' },
        { label: 'Pending', value: props.statusCounts.pending ?? 0, tone: 'text-white' },
        { label: 'Printing', value: props.statusCounts.printing ?? 0, tone: 'text-secondary' },
        { label: 'Completed', value: props.statusCounts.complete ?? 0, tone: 'text-emerald-300' },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <LuminousAppLayout
        active-nav="dashboard"
        :eyebrow="props.isAdmin ? 'Workshop Overview' : 'My Queue'"
        :title="props.isAdmin ? 'Request operations at a glance' : 'Your print queue, optimized for mobile.'"
        :intro="
            props.isAdmin
                ? 'Monitor pending work, jump into the request board, and send new invites without leaving the main dashboard.'
                : 'Start a new request quickly, then follow each print as it moves from review to production.'
        "
    >
        <template #pageActions>
            <Link v-if="props.isAdmin" :href="route('print-requests.index')" class="pill-button pill-button-secondary"> View request board </Link>
            <Link v-if="props.isAdmin" :href="route('admin.invite.create')" class="pill-button pill-button-primary">
                Invite user
                <UserPlus2 class="h-4 w-4" />
            </Link>

            <Link v-if="!props.isAdmin" :href="route('print-requests.index')" class="pill-button pill-button-secondary"> My requests </Link>
            <Link v-if="!props.isAdmin" :href="route('print-requests.create')" class="pill-button pill-button-primary">
                New request
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in metricCards" :key="card.label" class="luminous-panel px-5 py-5">
                <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-white/42 uppercase">{{ card.label }}</p>
                <p class="mt-4 font-display text-4xl font-semibold tracking-tight" :class="card.tone">{{ card.value }}</p>
            </article>
        </div>

        <section v-if="!props.isAdmin" class="mt-6 grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
            <article
                class="filament-glow relative overflow-hidden rounded-[2rem] bg-[linear-gradient(135deg,rgba(161,255,194,0.96),rgba(0,252,154,0.82))] px-6 py-6 text-[#06341c]"
            >
                <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/25 blur-3xl" />
                <p class="text-[0.72rem] font-semibold tracking-[0.24em] text-[#0f4f2c]/70 uppercase">Ready To Start</p>
                <h2 class="mt-3 font-display text-3xl font-semibold tracking-tight">Drop in files, add notes, send it.</h2>
                <p class="mt-3 max-w-xl text-sm leading-6 text-[#0d4a28]/80">
                    The new request flow keeps uploads front and center, with file limits and instructions visible before you submit.
                </p>
                <Link
                    :href="route('print-requests.create')"
                    class="mt-6 inline-flex min-h-12 items-center gap-2 rounded-full bg-[#082c18] px-5 text-sm font-semibold text-white"
                >
                    Start a new request
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </article>

            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-display text-2xl font-semibold tracking-tight text-white">Queue health</h2>
                    <Sparkles class="h-4 w-4 text-primary" />
                </div>
                <dl class="mt-6 space-y-4">
                    <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                        <dt class="text-muted-soft text-sm">Pending review</dt>
                        <dd class="font-display text-2xl text-white">{{ props.statusCounts.pending ?? 0 }}</dd>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                        <dt class="text-muted-soft text-sm">In production</dt>
                        <dd class="font-display text-2xl text-primary">{{ activeCount }}</dd>
                    </div>
                    <div class="flex items-center justify-between rounded-2xl bg-white/[0.04] px-4 py-4">
                        <dt class="text-muted-soft text-sm">Finished prints</dt>
                        <dd class="font-display text-2xl text-emerald-300">{{ props.statusCounts.complete ?? 0 }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <section v-if="props.isAdmin" class="mt-6 grid gap-4 lg:grid-cols-[1fr_1fr]">
            <article class="luminous-panel px-5 py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Workflow Snapshot</p>
                        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Move faster through the queue.</h2>
                    </div>
                    <Layers3 class="h-5 w-5 text-primary" />
                </div>
                <div class="mt-6 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Pending</p>
                        <p class="mt-2 font-display text-3xl text-white">{{ props.statusCounts.pending ?? 0 }}</p>
                    </div>
                    <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Accepted</p>
                        <p class="mt-2 font-display text-3xl text-secondary">{{ props.statusCounts.accepted ?? 0 }}</p>
                    </div>
                    <div class="rounded-3xl bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Printing</p>
                        <p class="mt-2 font-display text-3xl text-primary">{{ props.statusCounts.printing ?? 0 }}</p>
                    </div>
                </div>
            </article>

            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Admin Actions</p>
                        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Keep the workflow moving.</h2>
                    </div>
                    <FolderOpen class="h-5 w-5 text-secondary" />
                </div>
                <div class="mt-6 grid gap-3">
                    <Link :href="route('print-requests.index')" class="pill-button pill-button-secondary justify-between">
                        Review all requests
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link :href="route('admin.invite.create')" class="pill-button pill-button-secondary justify-between">
                        Send a new invite
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">
                            {{ props.isAdmin ? 'Latest Requests' : 'Recent Requests' }}
                        </p>
                        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">
                            {{ props.isAdmin ? 'Fresh work entering the board.' : 'Your current and recent prints.' }}
                        </h2>
                    </div>
                    <Link :href="route('print-requests.index')" class="text-sm font-medium text-white/65 hover:text-white"> View all </Link>
                </div>

                <div class="mt-6 grid gap-3">
                    <Link
                        v-for="requestItem in props.recentRequests"
                        :key="requestItem.id"
                        :href="route('print-requests.show', { print_request: requestItem.id })"
                        class="rounded-[1.45rem] bg-white/[0.04] px-4 py-4 transition-transform hover:-translate-y-0.5"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-display text-xl font-semibold tracking-tight text-white">Request #{{ requestItem.id }}</p>
                                    <StatusBadge :status="requestItem.status" />
                                </div>
                                <p class="text-muted-soft mt-2 text-sm leading-6">
                                    {{ requestItem.instructions || requestItem.source_url || 'No extra notes were added to this request.' }}
                                </p>
                                <div class="mt-3 flex flex-wrap items-center gap-3 text-xs tracking-[0.18em] text-white/42 uppercase">
                                    <span>{{ requestItem.files_count }} {{ requestItem.files_count === 1 ? 'file' : 'files' }}</span>
                                    <span>{{ formatDateTime(requestItem.created_at) }}</span>
                                    <span v-if="props.isAdmin && requestItem.user">{{ requestItem.user.name }}</span>
                                </div>
                            </div>

                            <ArrowRight class="mt-1 hidden h-4 w-4 shrink-0 text-white/35 sm:block" />
                        </div>
                    </Link>

                    <div v-if="!props.recentRequests.length" class="text-muted-soft rounded-[1.45rem] bg-white/[0.04] px-4 py-8 text-sm">
                        No requests have been submitted yet.
                    </div>
                </div>
            </article>

            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Recent Activity</p>
                        <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Queue events worth watching.</h2>
                    </div>
                    <Clock3 class="h-5 w-5 text-primary" />
                </div>

                <div class="mt-6 space-y-3">
                    <div v-for="activity in props.recentActivity" :key="activity.id" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <p class="font-display text-lg font-semibold tracking-tight text-white">{{ activity.title }}</p>
                            <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/40 uppercase">
                                {{ formatRelative(activity.at) }}
                            </span>
                        </div>
                        <p class="text-muted-soft mt-2 text-sm leading-6">{{ activity.description }}</p>
                        <Link
                            :href="route('print-requests.show', { print_request: activity.request_id })"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-primary"
                        >
                            Open request
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>

                    <div v-if="!props.recentActivity.length" class="text-muted-soft rounded-[1.35rem] bg-white/[0.04] px-4 py-8 text-sm">
                        Activity will appear here as requests move through the workflow.
                    </div>
                </div>
            </article>
        </section>
    </LuminousAppLayout>
</template>
