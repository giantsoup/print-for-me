<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { FolderOpen, LayoutGrid, PlusSquare, Settings2, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';

type NavKey = 'dashboard' | 'new' | 'requests' | 'settings';

interface Props {
    title: string;
    intro?: string;
    eyebrow?: string;
    activeNav: NavKey;
    wide?: boolean;
    showDock?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    intro: '',
    eyebrow: '',
    wide: false,
    showDock: true,
});

const page = usePage();
const user = computed(() => page.props.auth.user);
const isAdmin = computed(() => Boolean(user.value?.is_admin));

const desktopNav = computed(() => {
    if (isAdmin.value) {
        return [
            { key: 'dashboard', label: 'Dashboard', href: route('dashboard') },
            { key: 'requests', label: 'Requests', href: route('print-requests.index') },
            { key: 'settings', label: 'Settings', href: route('profile.edit') },
        ] as const;
    }

    return [
        { key: 'dashboard', label: 'Dashboard', href: route('dashboard') },
        { key: 'new', label: 'New Request', href: route('print-requests.create') },
        { key: 'requests', label: 'My Requests', href: route('print-requests.index') },
        { key: 'settings', label: 'Settings', href: route('profile.edit') },
    ] as const;
});

const dockNav = computed(() => {
    if (isAdmin.value) {
        return [
            { key: 'dashboard', label: 'Dashboard', href: route('dashboard'), icon: LayoutGrid },
            { key: 'requests', label: 'Requests', href: route('print-requests.index'), icon: FolderOpen },
            { key: 'settings', label: 'Settings', href: route('profile.edit'), icon: Settings2 },
        ] as const;
    }

    return [
        { key: 'dashboard', label: 'Dashboard', href: route('dashboard'), icon: LayoutGrid },
        { key: 'new', label: 'New', href: route('print-requests.create'), icon: PlusSquare },
        { key: 'requests', label: 'Requests', href: route('print-requests.index'), icon: FolderOpen },
    ] as const;
});

const containerClass = computed(() => (props.wide ? 'max-w-[88rem]' : 'max-w-6xl'));
</script>

<template>
    <div class="luminous-shell">
        <div class="relative z-10">
            <header class="sticky top-0 z-30 border-b border-white/6 bg-black/20 backdrop-blur-2xl">
                <div :class="containerClass" class="mx-auto flex items-center justify-between gap-4 px-4 py-3 sm:px-6 sm:py-4">
                    <Link :href="route('dashboard')" class="shrink-0">
                        <BrandMark />
                    </Link>

                    <nav class="hidden items-center gap-2 lg:flex">
                        <Link
                            v-for="item in desktopNav"
                            :key="item.key"
                            :href="item.href"
                            class="rounded-full px-4 py-2 text-sm font-medium text-white/60 hover:bg-white/[0.045] hover:text-white"
                            :class="item.key === props.activeNav ? 'bg-white/[0.06] text-white' : ''"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>

                    <div class="flex items-center gap-2">
                        <slot name="headerActions" />

                        <Link
                            v-if="isAdmin"
                            :href="route('admin.invite.create')"
                            class="hidden min-h-11 items-center rounded-full border border-white/8 bg-white/[0.045] px-4 text-sm font-semibold text-white/80 hover:bg-white/[0.08] lg:inline-flex"
                        >
                            Invite
                        </Link>

                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-white/8 bg-white/[0.045] text-white/80 hover:bg-white/[0.08] hover:text-white"
                        >
                            <UserRound class="h-4 w-4" />
                        </Link>

                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="hidden min-h-11 items-center rounded-full border border-white/8 bg-white/[0.045] px-4 text-sm font-medium text-white/70 hover:bg-white/[0.08] hover:text-white lg:inline-flex"
                        >
                            Log out
                        </Link>
                    </div>
                </div>
            </header>

            <main :class="containerClass" class="relative z-10 mx-auto px-4 pt-5 pb-40 sm:px-6 md:pt-6 md:pb-12">
                <section class="mb-6 flex flex-col gap-4 md:mb-8 md:flex-row md:items-end md:justify-between md:gap-5">
                    <div class="max-w-2xl">
                        <p v-if="props.eyebrow" class="mb-3 text-[0.72rem] font-semibold tracking-[0.24em] text-primary/75 uppercase">
                            {{ props.eyebrow }}
                        </p>
                        <h1 class="text-gradient-filament font-display text-[2rem] font-semibold tracking-tight sm:text-4xl">
                            {{ props.title }}
                        </h1>
                        <p v-if="props.intro" class="text-muted-soft mt-3 max-w-2xl text-sm leading-6 sm:text-base">
                            {{ props.intro }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <slot name="pageActions" />
                    </div>
                </section>

                <slot />
            </main>

            <nav v-if="props.showDock" class="fixed inset-x-3 bottom-3 z-30 lg:hidden">
                <div class="dock-surface mx-auto flex max-w-sm items-stretch justify-between gap-2 rounded-[1.6rem] border border-white/10 px-2 py-2">
                    <Link
                        v-for="item in dockNav"
                        :key="item.key"
                        :href="item.href"
                        class="flex min-h-12 flex-1 flex-col items-center justify-center gap-1 rounded-[1.1rem] px-2 py-2 text-[0.62rem] font-semibold tracking-[0.16em] text-white/45 uppercase"
                        :class="
                            item.key === props.activeNav
                                ? 'bg-primary/10 text-primary shadow-[inset_0_0_0_1px_rgba(161,255,194,0.12)]'
                                : 'hover:bg-white/[0.04] hover:text-white/80'
                        "
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        <span>{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
