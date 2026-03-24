<script setup lang="ts">
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateOnly, formatDateTime } from '@/lib/prints';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, ChevronDown, Search, Users } from 'lucide-vue-next';
import { computed, reactive, ref, watch } from 'vue';

interface UserListItem {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    access_state: string;
    created_at?: string | null;
    last_login_at?: string | null;
    deleted_at?: string | null;
    request_counts: {
        total: number;
        pending: number;
        accepted: number;
        printing: number;
        complete: number;
        deleted: number;
    };
}

interface Props {
    users: {
        data: UserListItem[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: {
        q: string;
        role: string;
        access: string;
        lifecycle: string;
        request_status: string;
    };
    summaryCounts: Record<string, number>;
    availableFilters: {
        roles: string[];
        access: string[];
        lifecycle: string[];
        requestStatuses: string[];
    };
}

const props = defineProps<Props>();
const page = usePage();

const filterForm = reactive({
    q: props.filters.q,
    role: props.filters.role,
    access: props.filters.access,
    lifecycle: props.filters.lifecycle,
    request_status: props.filters.request_status,
});
const isFilterPanelOpen = ref(false);

watch(
    () => props.filters,
    (value) => {
        filterForm.q = value.q;
        filterForm.role = value.role;
        filterForm.access = value.access;
        filterForm.lifecycle = value.lifecycle;
        filterForm.request_status = value.request_status;
    },
);

const flashStatus = computed(() => page.props.flash?.status);
const activeFilterCount = computed(
    () => [filterForm.q.trim(), filterForm.role, filterForm.access, filterForm.lifecycle, filterForm.request_status].filter(Boolean).length,
);

function applyFilters() {
    const query: Record<string, string> = {};

    if (filterForm.q.trim()) {
        query.q = filterForm.q.trim();
    }

    if (filterForm.role) {
        query.role = filterForm.role;
    }

    if (filterForm.access) {
        query.access = filterForm.access;
    }

    if (filterForm.lifecycle) {
        query.lifecycle = filterForm.lifecycle;
    }

    if (filterForm.request_status) {
        query.request_status = filterForm.request_status;
    }

    router.get(route('admin.users.index'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    filterForm.q = '';
    filterForm.role = '';
    filterForm.access = '';
    filterForm.lifecycle = '';
    filterForm.request_status = '';
    applyFilters();
}

function accessLabel(value: string) {
    switch (value) {
        case 'active':
            return 'Active';
        case 'revoked':
            return 'Revoked';
        case 'deleted':
            return 'Deleted';
        default:
            return 'Needs Access';
    }
}

function accessTone(value: string) {
    switch (value) {
        case 'active':
            return 'bg-primary/12 text-primary';
        case 'revoked':
            return 'bg-amber-400/12 text-amber-200';
        case 'deleted':
            return 'bg-rose-400/12 text-rose-200';
        default:
            return 'bg-white/[0.06] text-white/75';
    }
}

function roleTone(isAdmin: boolean) {
    return isAdmin ? 'bg-secondary/12 text-secondary' : 'bg-white/[0.06] text-white/72';
}

function labelize(value: string) {
    if (value === 'none') {
        return 'No Requests';
    }

    return value.charAt(0).toUpperCase() + value.slice(1).replace('_', ' ');
}
</script>

<template>
    <Head title="Manage Users" />

    <LuminousAppLayout active-nav="users" eyebrow="Users" title="Users" wide>
        <template #pageActions>
            <Link :href="route('admin.invite.create')" class="pill-button pill-button-primary w-full sm:w-auto">
                Invite
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <section class="grid grid-cols-2 gap-3 xl:grid-cols-5">
            <article class="luminous-panel w-full min-w-0 px-4 py-4 sm:px-5 sm:py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Users</p>
                <p class="mt-3 text-center text-3xl font-semibold tracking-tight text-white sm:font-display">{{ props.summaryCounts.all ?? 0 }}</p>
                <p class="text-muted-soft mt-2 text-sm">Total accounts</p>
            </article>

            <article class="luminous-panel w-full min-w-0 px-4 py-4 sm:px-5 sm:py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Admins</p>
                <p class="mt-3 text-center text-3xl font-semibold tracking-tight text-white sm:font-display">{{ props.summaryCounts.admins ?? 0 }}</p>
                <p class="text-muted-soft mt-2 text-sm">Administrators</p>
            </article>

            <article class="luminous-panel w-full min-w-0 px-4 py-4 sm:px-5 sm:py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Active Access</p>
                <p class="mt-3 text-center text-3xl font-semibold tracking-tight text-white sm:font-display">{{ props.summaryCounts.active ?? 0 }}</p>
                <p class="text-muted-soft mt-2 text-sm">Active accounts</p>
            </article>

            <article class="luminous-panel w-full min-w-0 px-4 py-4 sm:px-5 sm:py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Revoked</p>
                <p class="mt-3 text-center text-3xl font-semibold tracking-tight text-white sm:font-display">
                    {{ props.summaryCounts.revoked ?? 0 }}
                </p>
                <p class="text-muted-soft mt-2 text-sm">Revoked access</p>
            </article>

            <article
                class="luminous-panel col-span-2 w-full max-w-[16.5rem] justify-self-center px-4 py-4 sm:px-5 sm:py-5 xl:col-span-1 xl:max-w-none xl:justify-self-auto"
            >
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Deleted</p>
                <p class="mt-3 text-center text-3xl font-semibold tracking-tight text-white sm:font-display">
                    {{ props.summaryCounts.deleted ?? 0 }}
                </p>
                <p class="text-muted-soft mt-2 text-sm">Deleted accounts</p>
            </article>
        </section>

        <section class="luminous-panel mt-6 px-5 py-5">
            <button
                type="button"
                class="flex w-full items-center justify-between gap-3 text-left"
                :aria-expanded="isFilterPanelOpen"
                @click="isFilterPanelOpen = !isFilterPanelOpen"
            >
                <div class="space-y-1">
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Filters</p>
                    <p v-if="activeFilterCount" class="text-sm text-white/64">{{ `${activeFilterCount} active` }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full border border-white/10 bg-white/[0.04] px-3 py-1 text-xs font-semibold tracking-[0.16em] text-white/60 uppercase"
                    >
                        {{ isFilterPanelOpen ? 'Hide' : 'Show' }}
                    </span>
                    <ChevronDown class="h-4 w-4 text-white/55 transition-transform duration-200" :class="{ 'rotate-180': isFilterPanelOpen }" />
                </div>
            </button>

            <div v-if="isFilterPanelOpen" class="mt-5 border-t border-white/8 pt-5">
                <form class="grid gap-3 lg:grid-cols-[1.5fr_repeat(4,0.8fr)]" @submit.prevent="applyFilters">
                    <label class="grid gap-2">
                        <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Search</span>
                        <div class="flex min-h-12 items-center gap-3 rounded-[1.15rem] border border-white/8 bg-white/[0.04] px-4">
                            <Search class="h-4 w-4 text-white/45" />
                            <input
                                v-model="filterForm.q"
                                type="text"
                                placeholder="Name or email"
                                class="w-full bg-transparent text-sm text-white outline-none placeholder:text-white/30"
                            />
                        </div>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Role</span>
                        <select v-model="filterForm.role" class="luminous-input min-h-12">
                            <option value="">All roles</option>
                            <option v-for="role in props.availableFilters.roles" :key="role" :value="role">{{ labelize(role) }}</option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Access</span>
                        <select v-model="filterForm.access" class="luminous-input min-h-12">
                            <option value="">All access</option>
                            <option v-for="access in props.availableFilters.access" :key="access" :value="access">{{ labelize(access) }}</option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Lifecycle</span>
                        <select v-model="filterForm.lifecycle" class="luminous-input min-h-12">
                            <option value="">Active only</option>
                            <option v-for="lifecycle in props.availableFilters.lifecycle" :key="lifecycle" :value="lifecycle">
                                {{ labelize(lifecycle) }}
                            </option>
                        </select>
                    </label>

                    <label class="grid gap-2">
                        <span class="text-[0.68rem] font-semibold tracking-[0.18em] text-white/42 uppercase">Request Activity</span>
                        <select v-model="filterForm.request_status" class="luminous-input min-h-12">
                            <option value="">All request activity</option>
                            <option v-for="status in props.availableFilters.requestStatuses" :key="status" :value="status">
                                {{ labelize(status) }}
                            </option>
                        </select>
                    </label>

                    <div class="flex flex-col gap-3 sm:flex-row lg:col-span-5 lg:justify-end">
                        <button type="button" class="pill-button pill-button-secondary w-full sm:w-auto" @click="clearFilters">Clear</button>
                        <button type="submit" class="pill-button pill-button-primary w-full sm:w-auto">Apply filters</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="mt-6 grid gap-3">
            <article v-for="item in props.users.data" :key="item.id" class="luminous-panel px-5 py-5">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">{{ item.name }}</p>
                            <span
                                class="rounded-full px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] uppercase"
                                :class="roleTone(item.is_admin)"
                            >
                                {{ item.is_admin ? 'Admin' : 'Member' }}
                            </span>
                            <span
                                class="rounded-full px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] uppercase"
                                :class="accessTone(item.access_state)"
                            >
                                {{ accessLabel(item.access_state) }}
                            </span>
                        </div>

                        <p class="text-muted-soft mt-3 text-sm leading-6 break-words">{{ item.email }}</p>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                                <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Created</p>
                                <p class="mt-2 text-sm text-white">{{ formatDateOnly(item.created_at) }}</p>
                            </div>

                            <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                                <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Last Login</p>
                                <p class="mt-2 text-sm text-white">{{ formatDateTime(item.last_login_at) }}</p>
                            </div>

                            <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                                <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Requests</p>
                                <p class="mt-2 text-sm text-white">{{ item.request_counts.total }} total</p>
                                <p class="text-muted-soft mt-1 text-xs">
                                    Pending {{ item.request_counts.pending }}, printing {{ item.request_counts.printing }}
                                </p>
                            </div>

                            <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                                <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Deleted Requests</p>
                                <p class="mt-2 text-sm text-white">{{ item.request_counts.deleted }}</p>
                                <p class="text-muted-soft mt-1 text-xs">Completed {{ item.request_counts.complete }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex w-full flex-col gap-3 xl:w-auto xl:min-w-[13rem]">
                        <Link :href="route('admin.users.show', { user: item.id })" class="pill-button pill-button-secondary w-full justify-between">
                            Manage user
                            <ArrowRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </article>

            <div v-if="!props.users.data.length" class="luminous-panel px-5 py-10 text-center">
                <Users class="mx-auto h-5 w-5 text-primary" />
                <p class="mt-4 text-base font-medium text-white">No users found.</p>
                <p class="text-muted-soft mt-2 text-sm">Clear filters to view all users.</p>
            </div>
        </section>

        <nav v-if="props.users.links?.length" class="no-scrollbar mt-6 flex items-center gap-2 overflow-x-auto pb-1">
            <Link
                v-for="linkItem in props.users.links"
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
