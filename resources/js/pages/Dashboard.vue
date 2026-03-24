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

function summarizeRequest(requestItem: DashboardRequest): string {
    const summary = requestItem.instructions?.trim() || requestItem.source_url?.trim() || 'No details added.';

    if (summary.length <= 120) {
        return summary;
    }

    return `${summary.slice(0, 117).trimEnd()}...`;
}

const metricCards = computed(() => {
    if (props.isAdmin) {
        return [
            { label: 'All requests', value: props.statusCounts.all ?? 0, tone: 'text-white' },
            { label: 'In production', value: activeCount.value, tone: 'text-primary' },
            { label: 'Pending', value: props.statusCounts.pending ?? 0, tone: 'text-secondary' },
            { label: 'Completed', value: props.statusCounts.complete ?? 0, tone: 'text-emerald-300' },
        ];
    }

    return [
        { label: 'Open', value: activeCount.value + (props.statusCounts.pending ?? 0), tone: 'text-primary' },
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
        :eyebrow="props.isAdmin ? 'Overview' : 'My Queue'"
        title="Dashboard"
        :intro="props.isAdmin ? 'Review requests, track production, and manage access.' : 'Start a request and track progress in one place.'"
    >
        <template #pageActions>
            <Link v-if="props.isAdmin" :href="route('print-requests.index')" class="pill-button pill-button-primary w-full sm:w-auto">
                Open requests
                <ArrowRight class="h-4 w-4" />
            </Link>
            <Link v-if="props.isAdmin" :href="route('admin.invite.create')" class="pill-button pill-button-secondary w-full sm:w-auto">
                Invite user
                <UserPlus2 class="h-4 w-4" />
            </Link>

            <Link v-if="!props.isAdmin" :href="route('print-requests.create')" class="pill-button pill-button-primary w-full sm:w-auto">
                New request
                <ArrowRight class="h-4 w-4" />
            </Link>
            <Link v-if="!props.isAdmin" :href="route('print-requests.index')" class="pill-button pill-button-secondary w-full sm:w-auto">
                My requests
            </Link>
        </template>

        <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
            <article v-for="card in metricCards" :key="card.label" class="luminous-panel px-4 py-4 text-center sm:px-5 sm:py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">{{ card.label }}</p>
                <p class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl" :class="card.tone">{{ card.value }}</p>
            </article>
        </div>

        <section v-if="!props.isAdmin" class="mt-5 grid gap-3 lg:mt-6 lg:grid-cols-[1.1fr_0.9fr] lg:gap-4">
            <article
                class="filament-glow relative overflow-hidden rounded-[1.65rem] bg-[linear-gradient(135deg,rgba(161,255,194,0.96),rgba(0,252,154,0.82))] px-5 py-5 text-[#06341c] sm:rounded-[2rem] sm:px-6 sm:py-6"
            >
                <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/25 blur-3xl" />
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-[#0f4f2c]/70 uppercase">New request</p>
                <h2 class="mt-3 max-w-md text-[1.65rem] leading-tight font-semibold tracking-tight sm:font-display sm:text-3xl">
                    Upload files and send a request fast.
                </h2>
                <p class="mt-3 max-w-lg text-sm leading-5 text-[#0d4a28]/80 sm:leading-6">
                    Add files, a source link, and notes without leaving the flow.
                </p>
                <Link
                    :href="route('print-requests.create')"
                    class="mt-5 inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full bg-[#082c18] px-5 text-sm font-semibold text-white sm:mt-6 sm:w-auto"
                >
                    Start request
                    <ArrowRight class="h-4 w-4" />
                </Link>
            </article>

            <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">Queue status</h2>
                    <Sparkles class="h-4 w-4 text-primary" />
                </div>
                <dl class="mt-4 grid grid-cols-3 gap-2 sm:mt-6 sm:gap-3">
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-2xl sm:px-4 sm:py-4">
                        <dt class="text-muted-soft text-sm">Pending</dt>
                        <dd class="font-display text-2xl text-white">{{ props.statusCounts.pending ?? 0 }}</dd>
                    </div>
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-2xl sm:px-4 sm:py-4">
                        <dt class="text-muted-soft text-sm">In production</dt>
                        <dd class="font-display text-2xl text-primary">{{ activeCount }}</dd>
                    </div>
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-2xl sm:px-4 sm:py-4">
                        <dt class="text-muted-soft text-sm">Completed</dt>
                        <dd class="font-display text-2xl text-emerald-300">{{ props.statusCounts.complete ?? 0 }}</dd>
                    </div>
                </dl>
            </article>
        </section>

        <section v-if="props.isAdmin" class="mt-5 grid gap-3 lg:mt-6 lg:grid-cols-[1fr_1fr] lg:gap-4">
            <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Workflow</p>
                        <h2 class="mt-3 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">Current queue</h2>
                    </div>
                    <Layers3 class="h-5 w-5 text-primary" />
                </div>
                <div class="mt-4 grid grid-cols-3 gap-2 sm:mt-6 sm:gap-3">
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-3xl sm:px-4 sm:py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Pending</p>
                        <p class="mt-2 font-display text-[2rem] text-white sm:text-3xl">{{ props.statusCounts.pending ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-3xl sm:px-4 sm:py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Accepted</p>
                        <p class="mt-2 font-display text-[2rem] text-secondary sm:text-3xl">{{ props.statusCounts.accepted ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.4rem] bg-white/[0.04] px-3 py-3 text-center sm:rounded-3xl sm:px-4 sm:py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.2em] text-white/42 uppercase">Printing</p>
                        <p class="mt-2 font-display text-[2rem] text-primary sm:text-3xl">{{ props.statusCounts.printing ?? 0 }}</p>
                    </div>
                </div>
            </article>

            <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Actions</p>
                        <h2 class="mt-3 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">Admin tools</h2>
                    </div>
                    <FolderOpen class="h-5 w-5 text-secondary" />
                </div>
                <div class="mt-4 grid gap-3 sm:mt-6">
                    <Link :href="route('print-requests.index')" class="pill-button pill-button-secondary w-full justify-between">
                        Review requests
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                    <Link :href="route('admin.invite.create')" class="pill-button pill-button-secondary w-full justify-between">
                        Invite user
                        <ArrowRight class="h-4 w-4" />
                    </Link>
                </div>
            </article>
        </section>

        <section class="mt-5 grid gap-3 xl:mt-6 xl:grid-cols-[1.2fr_0.8fr] xl:gap-4">
            <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">
                            {{ props.isAdmin ? 'Recent requests' : 'My requests' }}
                        </p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:mt-3 sm:font-display sm:text-2xl">
                            {{ props.isAdmin ? 'Latest submissions' : 'Recent work' }}
                        </h2>
                    </div>
                    <Link :href="route('print-requests.index')" class="text-sm font-medium text-white/65 hover:text-white">View all</Link>
                </div>

                <div class="mt-4 grid gap-3 sm:mt-6">
                    <Link
                        v-for="requestItem in props.recentRequests"
                        :key="requestItem.id"
                        :href="route('print-requests.show', { print_request: requestItem.id })"
                        class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4 transition-transform hover:-translate-y-0.5"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-lg font-semibold tracking-tight text-white sm:font-display sm:text-xl">
                                        Request #{{ requestItem.id }}
                                    </p>
                                    <StatusBadge :status="requestItem.status" />
                                </div>
                                <p class="text-muted-soft mt-2 text-sm leading-5 sm:leading-6">
                                    {{ summarizeRequest(requestItem) }}
                                </p>
                                <div
                                    class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[0.68rem] tracking-[0.16em] text-white/42 uppercase"
                                >
                                    <span>{{ requestItem.files_count }} {{ requestItem.files_count === 1 ? 'file' : 'files' }}</span>
                                    <span>{{ formatDateTime(requestItem.created_at) }}</span>
                                    <span v-if="props.isAdmin && requestItem.user">{{ requestItem.user.name }}</span>
                                </div>
                            </div>

                            <ArrowRight class="mt-1 hidden h-4 w-4 shrink-0 text-white/35 sm:block" />
                        </div>
                    </Link>

                    <div v-if="!props.recentRequests.length" class="text-muted-soft rounded-[1.35rem] bg-white/[0.04] px-4 py-6 text-sm">
                        No requests yet.
                    </div>
                </div>
            </article>

            <article class="luminous-panel px-4 py-4 sm:px-5 sm:py-5">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Recent activity</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:mt-3 sm:font-display sm:text-2xl">Latest updates</h2>
                    </div>
                    <Clock3 class="h-5 w-5 text-primary" />
                </div>

                <div class="mt-4 space-y-3 sm:mt-6">
                    <div v-for="activity in props.recentActivity" :key="activity.id" class="rounded-[1.3rem] bg-white/[0.04] px-4 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between sm:gap-3">
                            <p class="text-base font-semibold tracking-tight text-white sm:font-display sm:text-lg">{{ activity.title }}</p>
                            <span class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">
                                {{ formatRelative(activity.at) }}
                            </span>
                        </div>
                        <p class="text-muted-soft mt-2 text-sm leading-5 sm:leading-6">{{ activity.description }}</p>
                        <Link
                            :href="route('print-requests.show', { print_request: activity.request_id })"
                            class="mt-3 inline-flex items-center gap-2 text-sm font-medium text-primary"
                        >
                            Open request
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>

                    <div v-if="!props.recentActivity.length" class="text-muted-soft rounded-[1.3rem] bg-white/[0.04] px-4 py-6 text-sm">
                        Activity appears here as requests move.
                    </div>
                </div>
            </article>
        </section>
    </LuminousAppLayout>
</template>
