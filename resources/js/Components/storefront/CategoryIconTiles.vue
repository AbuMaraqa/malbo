<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { brandIconFor } from './brandIcons';
import InitialTile from './InitialTile.vue';
import type { StoreCategory } from './types';

/**
 * The categories as the MALBO app shows them on phones: cream tiles, each with the brand
 * illustration its name points to (else its picture in a circle, else its first letter), the name
 * under it.
 */
defineProps<{ categories: StoreCategory[] }>();
</script>

<template>
    <ul class="grid grid-cols-2 gap-3">
        <li v-for="category in categories" :key="category.id">
            <Link
                :href="category.url"
                class="flex h-full flex-col items-center justify-center gap-2.5 rounded-2xl bg-(--sand) px-3 py-5 text-center transition-colors hover:bg-(--sand-deep)"
            >
                <img v-if="brandIconFor(category.name)" :src="brandIconFor(category.name) ?? undefined" alt="" class="h-14 w-auto" />
                <img v-else-if="category.image" :src="category.image" alt="" loading="lazy" class="size-14 rounded-full object-cover" />
                <span v-else class="grid size-14 place-items-center text-3xl"><InitialTile :name="category.name" /></span>
                <span class="text-[0.9375rem] leading-snug font-semibold">{{ category.name }}</span>
            </Link>
        </li>
    </ul>
</template>
