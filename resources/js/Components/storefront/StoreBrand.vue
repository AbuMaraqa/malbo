<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { route } from '@/lib/route';

/** The store's logo, or its name when it has none, linking home. */
defineProps<{ name: string; logo: string | null }>();
</script>

<template>
    <Link :href="route('home')" class="flex min-h-11 items-center font-bold">
        <img v-if="logo" :src="logo" :alt="name" class="h-10 w-auto max-w-44 object-contain" />
        <template v-else>{{ name }}</template>
    </Link>
</template>
