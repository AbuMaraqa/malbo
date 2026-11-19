<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { route } from '@/lib/route';

/**
 * The store's logo, or its name when it has none, linking home. The brand book's clear space (about
 * the height of the mark's "a", a little over a tenth of the logo's height) stays empty around it,
 * and it never shrinks under its digital minimum of 80px wide.
 */
defineProps<{ name: string; logo: string | null }>();
</script>

<template>
    <Link :href="route('home')" class="flex min-h-11 items-center p-1.5 font-bold">
        <img v-if="logo" :src="logo" :alt="name" class="h-10 w-auto max-w-44 min-w-20 object-contain" />
        <template v-else>{{ name }}</template>
    </Link>
</template>
