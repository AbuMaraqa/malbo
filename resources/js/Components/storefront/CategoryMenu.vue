<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { ChevronDown, Widgets } from '@/lib/icons';
import { route } from '@/lib/route';

import InitialTile from './InitialTile.vue';
import type { StoreCategory } from './types';

/**
 * The nav bar's "All categories" mega menu: a grid of the store's root categories with their
 * pictures. It opens on hover and on keyboard focus, so it needs no script.
 */
defineProps<{ categories: StoreCategory[] }>();
</script>

<template>
    <div class="group relative">
        <Link
            :href="route('store.shop')"
            class="flex h-12 items-center gap-2.5 bg-store px-5 font-semibold text-store-contrast transition-colors hover:bg-store-700"
            aria-haspopup="true"
        >
            <Widgets class="size-5" aria-hidden="true" />
            {{ t('All categories') }}
            <ChevronDown class="size-4 transition group-focus-within:rotate-180 group-hover:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </Link>

        <div
            class="invisible absolute start-0 top-full z-50 w-[min(44rem,calc(100vw-3rem))] translate-y-2 rounded-b-lg border border-t-0 border-line bg-white p-5 opacity-0 shadow-[0_24px_48px_-20px_rgb(42_21_26/0.35)] transition duration-200 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none"
        >
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <li v-for="category in categories" :key="category.id">
                    <Link :href="category.url" class="group/item flex items-center gap-3 rounded-md p-2 transition-colors hover:bg-(--sand-light)">
                        <span class="size-12 shrink-0 overflow-hidden rounded-md bg-(--sand)">
                            <img v-if="category.image" :src="category.image" alt="" loading="lazy" class="size-full object-cover" />
                            <InitialTile v-else :name="category.name" class="text-xl" />
                        </span>
                        <span class="min-w-0 truncate font-medium group-hover/item:text-store-600">{{ category.name }}</span>
                    </Link>
                </li>
            </ul>
            <Link :href="route('store.shop')" class="mt-4 flex items-center justify-center rounded-md bg-(--sand-light) py-2.5 text-sm font-semibold hover:text-store-600">
                {{ t('View all products') }}
            </Link>
        </div>
    </div>
</template>
