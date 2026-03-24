<script setup lang="ts">
import LuminousAppLayout from '@/layouts/LuminousAppLayout.vue';
import { formatDateTime } from '@/lib/prints';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ArrowRight, Globe, RefreshCcw } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface DomainItem {
    id: number;
    label: string;
    domain: string;
    policy: 'allow' | 'block';
    recommended_policy: 'allow' | 'block';
    last_seen_url?: string | null;
    last_seen_at?: string | null;
    last_attempted_at?: string | null;
    last_attempt_status?: 'success' | 'failure' | null;
    last_success_at?: string | null;
    last_failure_at?: string | null;
    can_attempt: boolean;
    is_seeded: boolean;
}

interface Props {
    domains: DomainItem[];
    summary: {
        allowed: number;
        blocked: number;
        tracked: number;
    };
}

const props = defineProps<Props>();
const page = usePage();
const pendingDomainId = ref<number | null>(null);
const pendingAction = ref<'policy' | 'attempt' | null>(null);

const flashStatus = computed(() => page.props.flash?.status);

function updatePolicy(domain: DomainItem, policy: 'allow' | 'block') {
    if (pendingDomainId.value !== null || domain.policy === policy) {
        return;
    }

    pendingDomainId.value = domain.id;
    pendingAction.value = 'policy';

    router.patch(
        route('admin.source-preview-domains.update', { source_preview_domain: domain.id }),
        { policy },
        {
            preserveScroll: true,
            onFinish: () => {
                pendingDomainId.value = null;
                pendingAction.value = null;
            },
        },
    );
}

function attemptFetch(domain: DomainItem) {
    if (pendingDomainId.value !== null || !domain.can_attempt) {
        return;
    }

    pendingDomainId.value = domain.id;
    pendingAction.value = 'attempt';

    router.post(
        route('admin.source-preview-domains.attempt', { source_preview_domain: domain.id }),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                pendingDomainId.value = null;
                pendingAction.value = null;
            },
        },
    );
}

function isBusy(domainId: number, action: 'policy' | 'attempt') {
    return pendingDomainId.value === domainId && pendingAction.value === action;
}
</script>

<template>
    <Head title="Preview Domains" />

    <LuminousAppLayout
        active-nav="requests"
        eyebrow="Admin"
        title="Preview domains"
        intro="Allow or block automatic preview fetching by domain, then retry the latest seen URL instantly when you need to test one."
        wide
    >
        <template #pageActions>
            <Link :href="route('print-requests.index')" class="pill-button pill-button-secondary w-full sm:w-auto">
                Review requests
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <section class="grid grid-cols-3 gap-2 sm:gap-3">
            <article class="luminous-panel px-3 py-3 text-center sm:px-5 sm:py-4">
                <p class="text-[0.62rem] font-semibold tracking-[0.16em] text-white/42 uppercase">Tracked</p>
                <p class="mt-2 font-display text-2xl font-semibold tracking-tight text-white sm:text-3xl">{{ props.summary.tracked }}</p>
            </article>
            <article class="luminous-panel px-3 py-3 text-center sm:px-5 sm:py-4">
                <p class="text-[0.62rem] font-semibold tracking-[0.16em] text-white/42 uppercase">Allowed</p>
                <p class="mt-2 font-display text-2xl font-semibold tracking-tight text-primary sm:text-3xl">{{ props.summary.allowed }}</p>
            </article>
            <article class="luminous-panel px-3 py-3 text-center sm:px-5 sm:py-4">
                <p class="text-[0.62rem] font-semibold tracking-[0.16em] text-white/42 uppercase">Blocked</p>
                <p class="mt-2 font-display text-2xl font-semibold tracking-tight text-rose-300 sm:text-3xl">{{ props.summary.blocked }}</p>
            </article>
        </section>

        <section class="mt-6 grid gap-3">
            <article v-for="domain in props.domains" :key="domain.id" class="luminous-panel px-5 py-5">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">{{ domain.label }}</p>
                                <span
                                    class="rounded-full px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] uppercase"
                                    :class="domain.policy === 'allow' ? 'bg-primary/12 text-primary' : 'bg-rose-500/12 text-rose-300'"
                                >
                                    {{ domain.policy }}
                                </span>
                                <span
                                    v-if="domain.is_seeded"
                                    class="rounded-full bg-white/[0.05] px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-white/50 uppercase"
                                >
                                    Popular default
                                </span>
                            </div>
                            <p class="mt-2 text-sm break-all text-white/55">{{ domain.domain }}</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="pill-button w-full justify-center sm:w-auto"
                                :class="domain.policy === 'allow' ? 'pill-button-primary' : 'pill-button-secondary'"
                                :disabled="pendingDomainId !== null"
                                @click="updatePolicy(domain, 'allow')"
                            >
                                {{ isBusy(domain.id, 'policy') && domain.policy !== 'allow' ? 'Saving...' : 'Allow' }}
                            </button>
                            <button
                                type="button"
                                class="pill-button w-full justify-center sm:w-auto"
                                :class="domain.policy === 'block' ? 'bg-rose-500/12 text-rose-300' : 'pill-button-secondary'"
                                :disabled="pendingDomainId !== null"
                                @click="updatePolicy(domain, 'block')"
                            >
                                {{ isBusy(domain.id, 'policy') && domain.policy !== 'block' ? 'Saving...' : 'Block' }}
                            </button>
                            <button
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-center sm:w-auto"
                                :disabled="pendingDomainId !== null || !domain.can_attempt"
                                @click="attemptFetch(domain)"
                            >
                                <RefreshCcw class="h-4 w-4" />
                                {{ isBusy(domain.id, 'attempt') ? 'Trying latest URL...' : 'Try latest URL' }}
                            </button>
                        </div>
                    </div>

                    <div class="grid gap-3 lg:grid-cols-[1.2fr_0.8fr]">
                        <div class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Latest URL</p>
                            <a
                                v-if="domain.last_seen_url"
                                :href="domain.last_seen_url"
                                target="_blank"
                                rel="noreferrer noopener"
                                class="mt-3 flex items-start gap-3 text-sm leading-6 break-all text-white/78 hover:text-white"
                            >
                                <Globe class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ domain.last_seen_url }}</span>
                            </a>
                            <p v-else class="mt-3 text-sm leading-6 text-white/45">No request URL has been seen for this domain yet.</p>
                            <p v-if="domain.last_seen_at" class="mt-3 text-xs tracking-[0.14em] text-white/35 uppercase">
                                Seen {{ formatDateTime(domain.last_seen_at) }}
                            </p>
                        </div>

                        <div class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Preview Health</p>
                            <p v-if="domain.last_attempt_status === 'success'" class="mt-3 text-sm font-medium text-primary">
                                Latest manual or automatic attempt succeeded.
                            </p>
                            <p v-else-if="domain.last_attempt_status === 'failure'" class="mt-3 text-sm font-medium text-rose-300">
                                Latest manual or automatic attempt failed.
                            </p>
                            <p v-else class="mt-3 text-sm text-white/45">No preview attempt has been recorded yet.</p>
                            <div class="mt-4 space-y-2 text-sm text-white/55">
                                <p v-if="domain.last_attempted_at">Last attempt: {{ formatDateTime(domain.last_attempted_at) }}</p>
                                <p v-if="domain.last_success_at">Last success: {{ formatDateTime(domain.last_success_at) }}</p>
                                <p v-if="domain.last_failure_at">Last failure: {{ formatDateTime(domain.last_failure_at) }}</p>
                                <p v-if="domain.policy !== domain.recommended_policy" class="text-secondary">
                                    Recommended default: {{ domain.recommended_policy }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </LuminousAppLayout>
</template>
