<script setup lang="ts">
const logoUrl = new URL('../../images/website-logo.png', import.meta.url).href;
</script>

<template>
    <img
        :src="logoUrl"
        alt="Taylor's Print Services logo"
        class="size-8 rounded-md object-contain ring-1 ring-black/10 dark:ring-white/10 bg-black/40"
        height="32"
        width="32"
        loading="eager"
        decoding="async"
    />
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">Taylor's Print Services</span>
    </div>
</template>
