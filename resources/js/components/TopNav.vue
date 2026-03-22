<script setup lang="ts">
import BrandMark from '@/components/luminous/BrandMark.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage();
const isAuthed = computed(() => Boolean((page.props as any).auth?.user));
const open = ref(false);

function toggle() {
    open.value = !open.value;
}

function close() {
    open.value = false;
}
</script>

<template>
    <header class="border-b border-white/6 bg-black/20 backdrop-blur-2xl">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <Link :href="isAuthed ? route('dashboard') : route('home')" class="shrink-0" @click="close">
                <BrandMark />
            </Link>

            <nav class="hidden items-center gap-2 md:flex">
                <Link
                    :href="isAuthed ? route('dashboard') : route('home')"
                    class="rounded-full px-4 py-2 text-sm font-medium text-white/60 hover:bg-white/[0.045] hover:text-white"
                >
                    {{ isAuthed ? 'Dashboard' : 'Home' }}
                </Link>
                <Link
                    :href="isAuthed ? route('print-requests.index') : route('login')"
                    class="rounded-full px-4 py-2 text-sm font-medium text-white/60 hover:bg-white/[0.045] hover:text-white"
                >
                    {{ isAuthed ? 'Requests' : 'Log in' }}
                </Link>
                <Link
                    :href="isAuthed ? route('print-requests.create') : route('login')"
                    class="pill-button pill-button-primary"
                >
                    {{ isAuthed ? 'New request' : 'Request login link' }}
                </Link>
            </nav>

            <button
                type="button"
                class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-full border border-white/8 bg-white/[0.045] text-sm font-medium text-white/75 md:hidden"
                @click="toggle"
            >
                {{ open ? 'Close' : 'Menu' }}
            </button>
        </div>

        <div v-if="open" class="border-t border-white/6 px-4 py-3 md:hidden sm:px-6">
            <nav class="grid gap-2">
                <Link
                    :href="isAuthed ? route('dashboard') : route('home')"
                    class="rounded-2xl bg-white/[0.045] px-4 py-3 text-sm font-medium text-white/80"
                    @click="close"
                >
                    {{ isAuthed ? 'Dashboard' : 'Home' }}
                </Link>
                <Link
                    :href="isAuthed ? route('print-requests.index') : route('login')"
                    class="rounded-2xl bg-white/[0.045] px-4 py-3 text-sm font-medium text-white/80"
                    @click="close"
                >
                    {{ isAuthed ? 'Requests' : 'Log in' }}
                </Link>
                <Link
                    :href="isAuthed ? route('print-requests.create') : route('login')"
                    class="pill-button pill-button-primary"
                    @click="close"
                >
                    {{ isAuthed ? 'New request' : 'Request login link' }}
                </Link>
            </nav>
        </div>
    </header>
</template>
