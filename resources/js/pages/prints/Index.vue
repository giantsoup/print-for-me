<script setup lang="ts">
import PrintRequestStateActions from '@/components/luminous/PrintRequestStateActions.vue';
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { differenceInCalendarDaysFromToday, formatDateOnly, formatDateTime, type PrintRequestActionKey } from '@/lib/prints';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ScanSearch, Sparkles } from 'lucide-vue-next';
import { computed } from 'vue';

interface RequestUser {
    name: string;
    email: string;
}

interface PrintRequestFile {
    id: number;
    original_name: string;
    size_bytes: number;
}

interface PrintRequestItem {
    id: number;
    status: string;
    source_url?: string | null;
    instructions?: string | null;
    needed_by_date?: string | null;
    created_at?: string;
    files?: PrintRequestFile[];
    files_count: number;
    availableStatusActions: PrintRequestActionKey[];
    user?: RequestUser | null;
}

interface Props {
    items: {
        data: PrintRequestItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    isAdmin: boolean;
    filters: { status?: string | null; urgency?: string | null };
    statuses: string[];
    statusCounts: Record<string, number>;
    urgencyCounts?: Record<string, number> | null;
}

const props = defineProps<Props>();
const page = usePage();

const statusFilters = computed(() => [
    { value: '', label: 'All', count: props.statusCounts.all ?? props.items.total },
    ...props.statuses.map((status) => ({
        value: status,
        label: status.charAt(0).toUpperCase() + status.slice(1),
        count: props.statusCounts[status] ?? 0,
    })),
]);
const urgencyFilters = computed(() => [
    { value: '', label: 'All', count: props.urgencyCounts?.all ?? props.items.total },
    { value: 'due_soon', label: 'Due Soon', count: props.urgencyCounts?.due_soon ?? 0 },
    { value: 'no_due_date', label: 'No Due Date', count: props.urgencyCounts?.no_due_date ?? 0 },
]);
const flashStatus = computed(() => page.props.flash?.status);
const showUrgencyFilters = computed(() => props.isAdmin && (props.filters.status || '') !== 'complete');

function filterByStatus(status: string | null) {
    router.get(route('print-requests.index'), buildFilterQuery(status, status === 'complete' ? null : props.filters.urgency || null), {
        preserveState: true,
        preserveScroll: true,
    });
}

function filterByUrgency(urgency: string | null) {
    router.get(route('print-requests.index'), buildFilterQuery(props.filters.status || null, urgency), {
        preserveState: true,
        preserveScroll: true,
    });
}

function buildFilterQuery(status: string | null, urgency: string | null) {
    const query: Record<string, string> = {};

    if (status) {
        query.status = status;
    }

    if (props.isAdmin && status !== 'complete' && urgency) {
        query.urgency = urgency;
    }

    return query;
}

function adminUrgencyLabel(item: PrintRequestItem) {
    if (!item.needed_by_date) {
        return 'No due date';
    }

    const daysUntil = differenceInCalendarDaysFromToday(item.needed_by_date);

    if (daysUntil !== null && daysUntil < 0) {
        return `Overdue · ${formatDateOnly(item.needed_by_date)}`;
    }

    if (daysUntil !== null && daysUntil <= 7) {
        return `Due soon · ${formatDateOnly(item.needed_by_date)}`;
    }

    return `Needed by ${formatDateOnly(item.needed_by_date)}`;
}

function adminUrgencyClass(item: PrintRequestItem) {
    if (!item.needed_by_date) {
        return 'text-white/42';
    }

    const daysUntil = differenceInCalendarDaysFromToday(item.needed_by_date);

    if (daysUntil !== null && daysUntil < 0) {
        return 'text-rose-300';
    }

    if (daysUntil !== null && daysUntil <= 7) {
        return 'text-secondary';
    }

    return 'text-white/58';
}
</script>

<template>
    <Head title="Print Requests" />

    <LuminousAppLayout
        active-nav="requests"
        :eyebrow="props.isAdmin ? 'Request Board' : 'My Requests'"
        :title="props.isAdmin ? 'Request Board' : 'My Requests'"
        :intro="
            props.isAdmin
                ? 'Use the board to review pending work, change status inline, and jump into any request detail screen.'
                : 'Filter the queue by status, open any request detail, and keep pending work updated before production starts.'
        "
    >
        <template #pageActions>
            <Link :href="route('print-requests.create')" class="pill-button pill-button-primary w-full sm:w-auto">
                {{ props.isAdmin ? 'Manual request' : 'New request' }}
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <section class="luminous-panel px-5 py-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div class="min-w-0">
                    <p class="text-[0.72rem] font-semibold tracking-[0.22em] text-primary/75 uppercase">Status Filters</p>
                    <h2 class="mt-3 max-w-2xl text-[1.6rem] leading-tight font-semibold tracking-tight text-white sm:font-display sm:text-2xl">
                        Filter the queue without losing context.
                    </h2>
                </div>
                <div class="text-muted-soft flex items-center gap-2 self-start text-sm">
                    <ScanSearch class="h-4 w-4 text-secondary" />
                    {{ props.items.total }} total {{ props.items.total === 1 ? 'request' : 'requests' }}
                </div>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Status</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                        <button
                            v-for="filter in statusFilters"
                            :key="filter.label"
                            type="button"
                            class="inline-flex min-h-12 w-full items-center justify-between gap-3 rounded-full px-4 py-3 text-sm font-semibold sm:w-auto sm:justify-start sm:px-5"
                            :class="
                                (props.filters.status || '') === filter.value
                                    ? 'bg-primary/12 text-primary'
                                    : 'bg-white/[0.05] text-white/68 hover:bg-white/[0.08] hover:text-white'
                            "
                            @click="filterByStatus(filter.value || null)"
                        >
                            <span>{{ filter.label }}</span>
                            <span class="shrink-0 rounded-full bg-black/20 px-2 py-0.5 text-[0.72rem]">{{ filter.count }}</span>
                        </button>
                    </div>
                </div>

                <div v-if="showUrgencyFilters">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Scheduling</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:gap-2">
                        <button
                            v-for="filter in urgencyFilters"
                            :key="filter.label"
                            type="button"
                            class="inline-flex min-h-12 w-full items-center justify-between gap-3 rounded-full px-4 py-3 text-sm font-semibold sm:w-auto sm:justify-start sm:px-5"
                            :class="
                                (props.filters.urgency || '') === filter.value
                                    ? 'bg-secondary/12 text-secondary'
                                    : 'bg-white/[0.05] text-white/68 hover:bg-white/[0.08] hover:text-white'
                            "
                            @click="filterByUrgency(filter.value || null)"
                        >
                            <span>{{ filter.label }}</span>
                            <span class="shrink-0 rounded-full bg-black/20 px-2 py-0.5 text-[0.72rem]">{{ filter.count }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <section class="mt-6 grid gap-3">
            <article v-for="item in props.items.data" :key="item.id" class="luminous-panel px-5 py-5">
                <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,28rem)] xl:items-start">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-col items-start gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-3">
                            <p class="text-xl leading-tight font-semibold tracking-tight text-white sm:font-display sm:text-2xl">
                                Request #{{ item.id }}
                            </p>
                            <StatusBadge :status="item.status" />
                        </div>

                        <p class="text-muted-soft mt-3 max-w-3xl text-sm leading-6 break-words">
                            {{ item.instructions || item.source_url || 'No extra notes were added to this request.' }}
                        </p>

                        <p
                            v-if="props.isAdmin"
                            class="mt-4 text-sm font-medium"
                            :class="adminUrgencyClass(item)"
                        >
                            {{ adminUrgencyLabel(item) }}
                        </p>

                        <div
                            class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-[0.72rem] font-semibold tracking-[0.18em] text-white/42 uppercase"
                        >
                            <span>{{ item.files_count }} {{ item.files_count === 1 ? 'file' : 'files' }}</span>
                            <span>{{ formatDateTime(item.created_at) }}</span>
                            <span v-if="!props.isAdmin && item.needed_by_date">Needed by {{ formatDateOnly(item.needed_by_date) }}</span>
                            <span v-if="props.isAdmin && item.user">{{ item.user.name }}</span>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-3 xl:self-start">
                        <Link
                            :href="route('print-requests.show', { print_request: item.id })"
                            class="pill-button pill-button-secondary w-full justify-between"
                        >
                            Open request
                            <ArrowRight class="h-4 w-4" />
                        </Link>

                        <PrintRequestStateActions
                            v-if="props.isAdmin"
                            :request-id="item.id"
                            :status="item.status"
                            :actions="item.availableStatusActions"
                            variant="compact"
                        />
                    </div>
                </div>
            </article>

            <div v-if="!props.items.data.length" class="luminous-panel px-5 py-10 text-center">
                <Sparkles class="mx-auto h-5 w-5 text-primary" />
                <p class="mt-4 text-base font-medium text-white">Nothing matches this filter yet.</p>
                <p class="text-muted-soft mt-2 text-sm">Clear the filter or create a new request to start filling the queue.</p>
            </div>
        </section>

        <nav v-if="props.items.links?.length" class="no-scrollbar mt-6 flex items-center gap-2 overflow-x-auto pb-1">
            <Link
                v-for="linkItem in props.items.links"
                :key="linkItem.label + linkItem.url"
                :href="linkItem.url || '#'"
                class="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm font-medium"
                :class="linkItem.active ? 'bg-primary/12 text-primary' : 'bg-white/[0.05] text-white/65 hover:bg-white/[0.08] hover:text-white'"
            >
                <span v-html="linkItem.label" />
            </Link>
        </nav>
    </LuminousAppLayout>
</template>
