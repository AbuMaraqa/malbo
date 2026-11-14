<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import InitialTile from './InitialTile.vue';
import type { StoreCategoryTile } from './types';

/** Categories as banner tiles: the picture, then the name and a "shop now" over a soft shade. */
defineProps<{ categories: StoreCategoryTile[] }>();
</script>

<template>
    <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="category in categories" :key="category.id">
            <Link :href="category.url" class="group relative block aspect-4/3 overflow-hidden rounded-xl bg-store-50">
                <img
                    v-if="category.image"
                    :src="category.image"
                    alt=""
                    loading="lazy"
                    class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none"
                />
                <InitialTile v-else :name="category.name" class="text-6xl" />
                <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-(--night)/75 via-(--night)/10 to-transparent"></span>
                <span class="absolute inset-x-0 bottom-0 p-6 text-white">
                    <span class="block text-xl font-bold">{{ category.name }}</span>
                    <span v-if="category.description" class="mt-1 line-clamp-2 block text-sm leading-6 text-white/80">{{ category.description }}</span>
                    <span class="mt-3 inline-flex items-center gap-2 text-sm font-semibold underline-offset-4 group-hover:underline">
                        {{ t('Shop now') }} <ArrowMark />
                    </span>
                </span>
            </Link>
        </li>
    </ul>
</template>
