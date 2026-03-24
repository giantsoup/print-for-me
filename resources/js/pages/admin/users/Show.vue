<script setup lang="ts">
import PrintRequestStateActions from '@/components/luminous/PrintRequestStateActions.vue';
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
import { formatDateOnly, formatDateTime, formatFileSize, type PrintRequestActionKey } from '@/lib/prints';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Download,
    LoaderCircle,
    Mail,
    RefreshCcw,
    ShieldAlert,
    ShieldCheck,
    ShieldMinus,
    ShieldPlus,
    Trash2,
    UserRoundCog,
} from 'lucide-vue-next';
import { computed, reactive, watch } from 'vue';

interface UserDetail {
    id: number;
    name: string;
    email: string;
    is_admin: boolean;
    access_state: string;
    created_at?: string | null;
    updated_at?: string | null;
    email_verified_at?: string | null;
    whitelisted_at?: string | null;
    access_revoked_at?: string | null;
    deleted_at?: string | null;
    last_login_at?: string | null;
    last_login_ip?: string | null;
    last_login_user_agent?: string | null;
}

interface RequestFile {
    id: number;
    original_name: string;
    size_bytes: number;
}

interface RequestItem {
    id: number;
    status: string;
    source_url?: string | null;
    instructions?: string | null;
    created_at?: string | null;
    deleted_at?: string | null;
    files_count: number;
    files: RequestFile[];
    availableStatusActions: PrintRequestActionKey[];
}

interface AuditEvent {
    id: number;
    event: string;
    title: string;
    description: string;
    actor: string;
    created_at?: string | null;
}

interface Props {
    user: UserDetail;
    security: {
        activeMagicTokens: number;
        sessionVersion: number;
    };
    requestCounts: Record<string, number>;
    requests: {
        data: RequestItem[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    requestFilters: {
        status: string;
        lifecycle: string;
    };
    auditEvents: AuditEvent[];
    availableActions: Record<string, boolean>;
}

const props = defineProps<Props>();
const page = usePage();

const requestFilterForm = reactive({
    status: props.requestFilters.status,
    lifecycle: props.requestFilters.lifecycle,
});

const updateForm = useForm({
    name: props.user.name,
    email: props.user.email,
});

const inviteForm = useForm<{ user?: string }>({});
const revokeAccessForm = useForm<{ user?: string }>({});
const restoreAccessForm = useForm<{ user?: string }>({});
const invalidateSessionsForm = useForm<{ user?: string }>({});
const promoteForm = useForm<{ user?: string }>({});
const demoteForm = useForm<{ user?: string }>({});
const deleteUserForm = useForm<{ user?: string }>({});
const restoreUserForm = useForm<{ user?: string }>({});
const purgeUserForm = useForm<{ confirm_email: string; confirm_purge: boolean; user?: string }>({
    confirm_email: '',
    confirm_purge: false,
});

watch(
    () => [props.user.name, props.user.email],
    ([name, email]) => {
        updateForm.defaults({ name, email });
        updateForm.reset();
        updateForm.clearErrors();
    },
);

watch(
    () => props.requestFilters,
    (value) => {
        requestFilterForm.status = value.status;
        requestFilterForm.lifecycle = value.lifecycle;
    },
);

const flashStatus = computed(() => page.props.flash?.status);
const destructiveError = computed(() => deleteUserForm.errors['user'] || purgeUserForm.errors['user'] || '');

function applyRequestFilters() {
    const query: Record<string, string> = {};

    if (requestFilterForm.status) {
        query.status = requestFilterForm.status;
    }

    if (requestFilterForm.lifecycle) {
        query.lifecycle = requestFilterForm.lifecycle;
    }

    router.get(route('admin.users.show', { user: props.user.id }), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function submitUpdate() {
    updateForm.patch(route('admin.users.update', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function inviteUser() {
    inviteForm.post(route('admin.users.invite', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function revokeAccess() {
    revokeAccessForm.post(route('admin.users.access.revoke', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function restoreAccess() {
    restoreAccessForm.post(route('admin.users.access.restore', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function invalidateSessions() {
    invalidateSessionsForm.post(route('admin.users.sessions.invalidate', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function promoteUser() {
    promoteForm.post(route('admin.users.role.promote', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function demoteUser() {
    demoteForm.post(route('admin.users.role.demote', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function deleteUser() {
    deleteUserForm.delete(route('admin.users.destroy', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function restoreUser() {
    restoreUserForm.post(route('admin.users.restore', { user: props.user.id }), {
        preserveScroll: true,
    });
}

function purgeUser() {
    purgeUserForm.delete(route('admin.users.purge', { user: props.user.id }), {
        preserveScroll: true,
        onSuccess: () => {
            purgeUserForm.reset();
        },
    });
}

function deleteRequest(item: RequestItem) {
    if (!confirm(`Delete request #${item.id}?`)) {
        return;
    }

    router.delete(route('print-requests.destroy', { print_request: item.id }), {
        preserveScroll: true,
    });
}

function restoreRequest(item: RequestItem) {
    router.patch(
        route('print-requests.restore', { print_request: item.id }),
        {},
        {
            preserveScroll: true,
        },
    );
}

function purgeRequest(item: RequestItem) {
    if (!confirm(`Permanently remove request #${item.id} and its files?`)) {
        return;
    }

    router.delete(route('print-requests.force-destroy', { id: item.id }), {
        preserveScroll: true,
    });
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
            return 'bg-white/[0.06] text-white/72';
    }
}
</script>

<template>
    <Head :title="user.name" />

    <LuminousAppLayout
        active-nav="users"
        eyebrow="User"
        :title="user.name"
        :intro="user.email"
        wide
    >
        <template #pageActions>
            <Link :href="route('admin.users.index')" class="pill-button pill-button-secondary w-full sm:w-auto">
                <ArrowLeft class="h-4 w-4" />
                Users
            </Link>
            <Link :href="route('print-requests.index')" class="pill-button pill-button-secondary w-full sm:w-auto">
                Requests
                <ArrowRight class="h-4 w-4" />
            </Link>
        </template>

        <div v-if="flashStatus" class="mb-6 rounded-[1.45rem] border border-primary/12 bg-primary/10 px-5 py-4 text-sm text-primary">
            {{ flashStatus }}
        </div>

        <section class="grid gap-3 xl:grid-cols-[1.25fr_0.75fr]">
            <article class="luminous-panel px-5 py-5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] uppercase" :class="user.is_admin ? 'bg-secondary/12 text-secondary' : 'bg-white/[0.06] text-white/72'">
                        {{ user.is_admin ? 'Admin' : 'Member' }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] uppercase" :class="accessTone(user.access_state)">
                        {{ accessLabel(user.access_state) }}
                    </span>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Created</p>
                        <p class="mt-2 text-sm text-white">{{ formatDateOnly(user.created_at) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Verified</p>
                        <p class="mt-2 text-sm text-white">{{ formatDateTime(user.email_verified_at) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Access Granted</p>
                        <p class="mt-2 text-sm text-white">{{ formatDateTime(user.whitelisted_at) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Last Login</p>
                        <p class="mt-2 text-sm text-white">{{ formatDateTime(user.last_login_at) }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Last IP</p>
                        <p class="mt-2 break-words text-sm text-white">{{ user.last_login_ip || 'Unavailable' }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Deleted</p>
                        <p class="mt-2 text-sm text-white">{{ formatDateTime(user.deleted_at) }}</p>
                    </div>
                </div>

                <div class="mt-4 rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                    <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Last User Agent</p>
                    <p class="mt-2 text-sm leading-6 text-white/80">{{ user.last_login_user_agent || 'Unavailable' }}</p>
                </div>
            </article>

            <article class="luminous-panel px-5 py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Security</p>
                <div class="mt-4 grid gap-3">
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Active Magic Links</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-white sm:font-display">{{ security.activeMagicTokens }}</p>
                    </div>

                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Session Version</p>
                        <p class="mt-2 text-2xl font-semibold tracking-tight text-white sm:font-display">{{ security.sessionVersion }}</p>
                    </div>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-3 xl:grid-cols-[1fr_1fr]">
            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center gap-3">
                    <UserRoundCog class="h-5 w-5 text-primary" />
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Account</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">Details</h2>
                    </div>
                </div>

                <form class="mt-5 grid gap-4" @submit.prevent="submitUpdate">
                    <label class="grid gap-2">
                        <span class="field-label">Name</span>
                        <input v-model="updateForm.name" type="text" class="luminous-input" :disabled="!availableActions.canUpdate" />
                        <p v-if="updateForm.errors.name" class="text-sm text-rose-300">{{ updateForm.errors.name }}</p>
                    </label>

                    <label class="grid gap-2">
                        <span class="field-label">Email</span>
                        <input v-model="updateForm.email" type="email" class="luminous-input" :disabled="!availableActions.canUpdate" />
                        <p v-if="updateForm.errors.email" class="text-sm text-rose-300">{{ updateForm.errors.email }}</p>
                    </label>

                    <p class="text-muted-soft text-sm leading-6">Changing the email clears verification, signs out active sessions, and requires a new invite.</p>

                    <button
                        type="submit"
                        :disabled="!availableActions.canUpdate || updateForm.processing || !updateForm.isDirty"
                        class="pill-button pill-button-primary w-full sm:w-auto disabled:cursor-not-allowed disabled:opacity-45"
                    >
                        <LoaderCircle v-if="updateForm.processing" class="h-4 w-4 animate-spin" />
                        Save account changes
                    </button>
                </form>
            </article>

            <article class="luminous-panel px-5 py-5">
                <div class="flex items-center gap-3">
                    <ShieldCheck class="h-5 w-5 text-secondary" />
                    <div>
                        <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Actions</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">Access and role</h2>
                    </div>
                </div>

                <div class="mt-5 grid gap-3">
                    <button
                        v-if="availableActions.canInvite"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="inviteForm.processing"
                        @click="inviteUser"
                    >
                        <span class="inline-flex items-center gap-2"><Mail class="h-4 w-4" /> Send magic link</span>
                        <LoaderCircle v-if="inviteForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <button
                        v-if="availableActions.canRevokeAccess"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="revokeAccessForm.processing"
                        @click="revokeAccess"
                    >
                        <span class="inline-flex items-center gap-2"><ShieldAlert class="h-4 w-4" /> Revoke access</span>
                        <LoaderCircle v-if="revokeAccessForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <button
                        v-if="availableActions.canRestoreAccess"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="restoreAccessForm.processing"
                        @click="restoreAccess"
                    >
                        <span class="inline-flex items-center gap-2"><RefreshCcw class="h-4 w-4" /> Restore access</span>
                        <LoaderCircle v-if="restoreAccessForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <button
                        v-if="availableActions.canInvalidateSessions"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="invalidateSessionsForm.processing"
                        @click="invalidateSessions"
                    >
                        <span class="inline-flex items-center gap-2"><ShieldAlert class="h-4 w-4" /> Force sign-out</span>
                        <LoaderCircle v-if="invalidateSessionsForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <button
                        v-if="availableActions.canPromote"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="promoteForm.processing"
                        @click="promoteUser"
                    >
                        <span class="inline-flex items-center gap-2"><ShieldPlus class="h-4 w-4" /> Promote to admin</span>
                        <LoaderCircle v-if="promoteForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <button
                        v-if="availableActions.canDemote"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="demoteForm.processing"
                        @click="demoteUser"
                    >
                        <span class="inline-flex items-center gap-2"><ShieldMinus class="h-4 w-4" /> Remove admin role</span>
                        <LoaderCircle v-if="demoteForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <p
                        v-if="inviteForm.errors['user'] || revokeAccessForm.errors['user'] || restoreAccessForm.errors['user'] || invalidateSessionsForm.errors['user'] || demoteForm.errors['user']"
                        class="text-sm text-rose-300"
                    >
                        {{
                            inviteForm.errors['user'] ||
                            revokeAccessForm.errors['user'] ||
                            restoreAccessForm.errors['user'] ||
                            invalidateSessionsForm.errors['user'] ||
                            demoteForm.errors['user']
                        }}
                    </p>
                </div>
            </article>
        </section>

        <section class="mt-6 grid gap-3 xl:grid-cols-[0.85fr_1.15fr]">
            <article class="luminous-panel px-5 py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Requests</p>
                <div class="mt-5 grid grid-cols-2 gap-3">
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Total</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.all ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Files</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.files ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Pending</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.pending ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Accepted</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.accepted ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Printing</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.printing ?? 0 }}</p>
                    </div>
                    <div class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                        <p class="text-[0.68rem] font-semibold tracking-[0.16em] text-white/40 uppercase">Deleted</p>
                        <p class="mt-2 text-2xl font-semibold text-white sm:font-display">{{ requestCounts.deleted ?? 0 }}</p>
                    </div>
                </div>
            </article>

            <article class="luminous-panel px-5 py-5">
                <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Lifecycle</p>
                <div class="mt-5 grid gap-3">
                    <Dialog v-if="availableActions.canDelete">
                        <DialogTrigger as-child>
                            <button type="button" class="pill-button pill-button-secondary w-full justify-between">
                                <span class="inline-flex items-center gap-2"><Trash2 class="h-4 w-4" /> Delete user</span>
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </DialogTrigger>

                        <DialogContent>
                            <form class="space-y-6" @submit.prevent="deleteUser">
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>Delete this account?</DialogTitle>
                                    <DialogDescription>
                                        The account will be soft-deleted, all current access will be revoked, and the user will need to be restored before any further changes.
                                    </DialogDescription>
                                </DialogHeader>

                                <p v-if="deleteUserForm.errors['user']" class="text-sm text-rose-300">{{ deleteUserForm.errors['user'] }}</p>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <button type="button" class="pill-button pill-button-secondary w-full sm:w-auto">Cancel</button>
                                    </DialogClose>
                                    <button type="submit" class="pill-button pill-button-primary w-full sm:w-auto" :disabled="deleteUserForm.processing">
                                        <LoaderCircle v-if="deleteUserForm.processing" class="h-4 w-4 animate-spin" />
                                        Delete user
                                    </button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>

                    <button
                        v-if="availableActions.canRestoreUser"
                        type="button"
                        class="pill-button pill-button-secondary w-full justify-between"
                        :disabled="restoreUserForm.processing"
                        @click="restoreUser"
                    >
                        <span class="inline-flex items-center gap-2"><RefreshCcw class="h-4 w-4" /> Restore user</span>
                        <LoaderCircle v-if="restoreUserForm.processing" class="h-4 w-4 animate-spin" />
                    </button>

                    <Dialog v-if="availableActions.canPurge">
                        <DialogTrigger as-child>
                            <button type="button" class="pill-button pill-button-secondary w-full justify-between">
                                <span class="inline-flex items-center gap-2"><Trash2 class="h-4 w-4" /> Permanently purge</span>
                                <ArrowRight class="h-4 w-4" />
                            </button>
                        </DialogTrigger>

                        <DialogContent>
                            <form class="space-y-6" @submit.prevent="purgeUser">
                                <DialogHeader class="space-y-3">
                                    <DialogTitle>Permanently purge this account?</DialogTitle>
                                    <DialogDescription>
                                        This removes the account, all related print requests, all attached files, and all active magic-login links. This cannot be undone.
                                    </DialogDescription>
                                </DialogHeader>

                                <label class="grid gap-2">
                                    <span class="field-label">Type {{ user.email }} to confirm</span>
                                    <input v-model="purgeUserForm.confirm_email" type="email" class="luminous-input" />
                                    <p v-if="purgeUserForm.errors.confirm_email" class="text-sm text-rose-300">{{ purgeUserForm.errors.confirm_email }}</p>
                                </label>

                                <label class="flex items-start gap-3 rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                                    <input v-model="purgeUserForm.confirm_purge" type="checkbox" class="mt-1 h-4 w-4 rounded border-white/20 bg-transparent" />
                                    <span class="text-sm leading-6 text-white/80">I understand that related requests, file records, stored files, and magic links will be removed.</span>
                                </label>
                                <p v-if="purgeUserForm.errors.confirm_purge" class="text-sm text-rose-300">{{ purgeUserForm.errors.confirm_purge }}</p>
                                <p v-if="destructiveError" class="text-sm text-rose-300">{{ destructiveError }}</p>

                                <DialogFooter class="gap-2">
                                    <DialogClose as-child>
                                        <button type="button" class="pill-button pill-button-secondary w-full sm:w-auto">Cancel</button>
                                    </DialogClose>
                                    <button type="submit" class="pill-button pill-button-primary w-full sm:w-auto" :disabled="purgeUserForm.processing">
                                        <LoaderCircle v-if="purgeUserForm.processing" class="h-4 w-4 animate-spin" />
                                        Permanently purge
                                    </button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>
            </article>
        </section>

        <section class="luminous-panel mt-6 px-5 py-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Requests</p>
                    <h2 class="mt-2 text-xl font-semibold tracking-tight text-white sm:font-display sm:text-2xl">History and files</h2>
                </div>

                <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="applyRequestFilters">
                    <select v-model="requestFilterForm.status" class="luminous-input min-h-12">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="accepted">Accepted</option>
                        <option value="printing">Printing</option>
                        <option value="complete">Complete</option>
                    </select>

                    <select v-model="requestFilterForm.lifecycle" class="luminous-input min-h-12">
                        <option value="">Active only</option>
                        <option value="all">All requests</option>
                        <option value="deleted">Deleted only</option>
                    </select>

                    <button type="submit" class="pill-button pill-button-secondary w-full sm:col-span-2">Apply request filters</button>
                </form>
            </div>

            <div class="mt-6 grid gap-3">
                <article v-for="item in requests.data" :key="item.id" class="rounded-[1.35rem] bg-white/[0.04] px-4 py-4">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-lg font-semibold tracking-tight text-white sm:font-display sm:text-xl">Request #{{ item.id }}</p>
                                <StatusBadge :status="item.status" />
                                <span
                                    v-if="item.deleted_at"
                                    class="rounded-full bg-rose-400/12 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.16em] text-rose-200 uppercase"
                                >
                                    Deleted
                                </span>
                            </div>

                            <p class="text-muted-soft mt-3 text-sm leading-6">
                                {{ item.instructions || item.source_url || 'No extra notes were added to this request.' }}
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link
                                    v-for="file in item.files"
                                    :key="file.id"
                                    :href="route('print-requests.files.download', { print_request: item.id, file: file.id })"
                                    class="inline-flex items-center gap-2 rounded-full bg-white/[0.06] px-3 py-2 text-xs font-medium text-white/80 hover:bg-white/[0.1]"
                                >
                                    <Download class="h-3.5 w-3.5" />
                                    {{ file.original_name }} · {{ formatFileSize(file.size_bytes) }}
                                </Link>
                            </div>

                            <p class="text-muted-soft mt-4 text-xs tracking-[0.14em] uppercase">
                                {{ formatDateTime(item.created_at) }} · {{ item.files_count }} {{ item.files_count === 1 ? 'file' : 'files' }}
                            </p>
                        </div>

                        <div class="flex w-full flex-col gap-3 xl:w-[18rem]">
                            <Link :href="route('print-requests.show', { print_request: item.id })" class="pill-button pill-button-secondary w-full justify-between">
                                Open request
                                <ArrowRight class="h-4 w-4" />
                            </Link>

                            <PrintRequestStateActions
                                v-if="!item.deleted_at"
                                :request-id="item.id"
                                :status="item.status"
                                :actions="item.availableStatusActions"
                                variant="compact"
                            />

                            <button
                                v-if="!item.deleted_at"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-between"
                                @click="deleteRequest(item)"
                            >
                                Delete request
                                <Trash2 class="h-4 w-4" />
                            </button>

                            <button
                                v-if="item.deleted_at"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-between"
                                @click="restoreRequest(item)"
                            >
                                Restore request
                                <RefreshCcw class="h-4 w-4" />
                            </button>

                            <button
                                v-if="item.deleted_at"
                                type="button"
                                class="pill-button pill-button-secondary w-full justify-between"
                                @click="purgeRequest(item)"
                            >
                                Permanently purge request
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </article>

                <div v-if="!requests.data.length" class="rounded-[1.35rem] bg-white/[0.04] px-5 py-10 text-center">
                    <p class="text-base font-medium text-white">No requests found.</p>
                    <p class="text-muted-soft mt-2 text-sm">Adjust the filters to view requests.</p>
                </div>
            </div>

            <nav v-if="requests.links?.length" class="no-scrollbar mt-6 flex items-center gap-2 overflow-x-auto pb-1">
                <Link
                    v-for="linkItem in requests.links"
                    :key="linkItem.label + linkItem.url"
                    :href="linkItem.url || '#'"
                    class="inline-flex min-h-11 shrink-0 items-center rounded-full px-4 text-sm font-medium"
                    :class="linkItem.active ? 'bg-primary/12 text-primary' : 'bg-white/[0.05] text-white/65 hover:bg-white/[0.08] hover:text-white'"
                >
                    <span v-html="linkItem.label" />
                </Link>
            </nav>
        </section>

        <section class="luminous-panel mt-6 px-5 py-5">
            <p class="text-[0.68rem] font-semibold tracking-[0.18em] text-primary/75 uppercase">Activity</p>
            <div class="mt-5 grid gap-3">
                <article v-for="event in auditEvents" :key="event.id" class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-medium text-white">{{ event.title }}</p>
                            <p class="text-muted-soft mt-2 text-sm leading-6">{{ event.description }}</p>
                        </div>

                        <div class="text-right">
                            <p class="text-sm text-white/80">{{ event.actor }}</p>
                            <p class="text-muted-soft mt-1 text-xs tracking-[0.14em] uppercase">{{ formatDateTime(event.created_at) }}</p>
                        </div>
                    </div>
                </article>

                <div v-if="!auditEvents.length" class="rounded-[1.2rem] bg-white/[0.04] px-4 py-4">
                    <p class="font-medium text-white">No activity recorded.</p>
                </div>
            </div>
        </section>
    </LuminousAppLayout>
</template>
