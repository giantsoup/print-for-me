<script setup lang="ts">
import StatusBadge from '@/components/luminous/StatusBadge.vue';
import { formatDateTime } from '@/lib/prints';
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, CheckCircle2, RotateCcw, ScanSearch, Sparkles } from 'lucide-vue-next';
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
    created_at?: string;
    files?: PrintRequestFile[];
    files_count: number;
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
    filters: { status?: string | null };
    statuses: string[];
    statusCounts: Record<string, number>;
}

const props = defineProps<Props>();

const statusFilters = computed(() => [
    { value: '', label: 'All', count: props.statusCounts.all ?? props.items.total },
    ...props.statuses.map((status) => ({
        value: status,
        label: status.charAt(0).toUpperCase() + status.slice(1),
        count: props.statusCounts[status] ?? 0,
    })),
]);

function filterByStatus(status: string | null) {
    const query: Record<string, string> = {};

    if (status) {
        query.status = status;
    }

    router.get(route('print-requests.index'), query, {
        preserveState: true,
        preserveScroll: true,
    });
}

function adminAction(action: 'accept' | 'printing' | 'complete' | 'revert', id: number) {
    const routeName = {
        accept: 'admin.print-requests.accept',
        printing: 'admin.print-requests.printing',
        complete: 'admin.print-requests.complete',
        revert: 'admin.print-requests.revert',
    }[action];

    router.patch(
        route(routeName, { print_request: id }),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => router.reload(),
        },
    );
}
</script>

<template>
    <Head title="Print Requests" />

    <LuminousAppLayout
        active-nav="requests"
        :eyebrow="props.isAdmin ? 'Request Board' : 'My Requests'"
        :title="props.isAdmin ? 'Review the queue, then move work forward.' : 'Every request in one clear mobile-first list.'"
        :intro="
            props.isAdmin
                ? 'Use the board to review pending work, change status inline, and jump into any request detail screen.'
                : 'Filter the queue by status, open any request detail, and keep pending work updated before production starts.'
        "
    >
        <template #pageActions>
            <Link
                :href="route('print-requests.create')"
                class="pill-button pill-button-primary"
            >
                {{ props.isAdmin ? 'Manual request' : 'New request' }}
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <section class="luminous-panel px-5 py-5">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-[0.72rem] font-semibold uppercase tracking-[0.22em] text-primary/75">Status Filters</p>
                    <h2 class="mt-3 font-display text-2xl font-semibold tracking-tight text-white">Filter the queue without losing context.</h2>
                </div>
                <div class="flex items-center gap-2 text-sm text-muted-soft">
                    <ScanSearch class="h-4 w-4 text-secondary" />
                    {{ props.items.total }} total {{ props.items.total === 1 ? 'request' : 'requests' }}
                </div>
            </div>

            <div class="mt-6 flex gap-2 overflow-x-auto pb-2 no-scrollbar">
                <button
                    v-for="filter in statusFilters"
                    :key="filter.label"
                    type="button"
                    class="inline-flex min-h-12 items-center gap-3 rounded-full px-5 py-3 text-sm font-semibold"
                    :class="
                        (props.filters.status || '') === filter.value
                            ? 'bg-primary/12 text-primary'
                            : 'bg-white/[0.05] text-white/68 hover:bg-white/[0.08] hover:text-white'
                    "
                    @click="filterByStatus(filter.value || null)"
                >
                    <span>{{ filter.label }}</span>
                    <span class="rounded-full bg-black/20 px-2 py-0.5 text-[0.72rem]">{{ filter.count }}</span>
                </button>
            </div>
        </section>

        <section class="mt-6 grid gap-3">
            <article
                v-for="item in props.items.data"
                :key="item.id"
                class="luminous-panel px-5 py-5"
            >
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-3">
                            <p class="font-display text-2xl font-semibold tracking-tight text-white">Request #{{ item.id }}</p>
                            <StatusBadge :status="item.status" />
                        </div>

                        <p class="mt-3 max-w-3xl text-sm leading-6 text-muted-soft">
                            {{ item.instructions || item.source_url || 'No extra notes were added to this request.' }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-3 text-[0.72rem] font-semibold uppercase tracking-[0.18em] text-white/42">
                            <span>{{ item.files_count }} {{ item.files_count === 1 ? 'file' : 'files' }}</span>
                            <span>{{ formatDateTime(item.created_at) }}</span>
                            <span v-if="props.isAdmin && item.user">{{ item.user.name }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 xl:min-w-[17rem]">
                        <Link
                            :href="route('print-requests.show', { print_request: item.id })"
                            class="pill-button pill-button-secondary w-full justify-between"
                        >
                            Open request
                            <ArrowRight class="h-4 w-4" />
                        </Link>

                        <div v-if="props.isAdmin" class="grid gap-2 sm:grid-cols-2">
                            <button
                                type="button"
                                class="pill-button w-full justify-center bg-primary/10 text-primary disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="item.status !== 'pending'"
                                @click="adminAction('accept', item.id)"
                            >
                                Accept
                            </button>
                            <button
                                type="button"
                                class="pill-button w-full justify-center bg-secondary/12 text-secondary disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="item.status !== 'accepted'"
                                @click="adminAction('printing', item.id)"
                            >
                                Start
                            </button>
                            <button
                                type="button"
                                class="pill-button w-full justify-center bg-emerald-400/10 text-emerald-300 disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="item.status !== 'printing'"
                                @click="adminAction('complete', item.id)"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                Complete
                            </button>
                            <button
                                type="button"
                                class="pill-button w-full justify-center bg-white/[0.06] text-white/72 disabled:cursor-not-allowed disabled:opacity-35"
                                :disabled="!(item.status === 'accepted' || item.status === 'printing')"
                                @click="adminAction('revert', item.id)"
                            >
                                <RotateCcw class="h-4 w-4" />
                                Revert
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <div v-if="!props.items.data.length" class="luminous-panel px-5 py-10 text-center">
                <Sparkles class="mx-auto h-5 w-5 text-primary" />
                <p class="mt-4 text-base font-medium text-white">Nothing matches this filter yet.</p>
                <p class="mt-2 text-sm text-muted-soft">
                    Clear the filter or create a new request to start filling the queue.
                </p>
            </div>
        </section>

        <nav v-if="props.items.links?.length" class="mt-6 flex flex-wrap items-center gap-2">
            <Link
                v-for="linkItem in props.items.links"
                :key="linkItem.label + linkItem.url"
                :href="linkItem.url || '#'"
                class="inline-flex min-h-11 items-center rounded-full px-4 text-sm font-medium"
                :class="
                    linkItem.active
                        ? 'bg-primary/12 text-primary'
                        : 'bg-white/[0.05] text-white/65 hover:bg-white/[0.08] hover:text-white'
                "
            >
                <span v-html="linkItem.label" />
            </Link>
        </nav>
    </LuminousAppLayout>
</template>
