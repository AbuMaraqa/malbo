<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { ChevronDown } from '@/lib/icons';
import { route } from '@/lib/route';

import InitialTile from './InitialTile.vue';
import type { StoreCategory } from './types';

/**
 * The nav's "Shop" entry with its mega menu: the store's root categories as square pictures with
 * their names. It opens on hover and on keyboard focus, so it needs no script.
 */
defineProps<{ categories: StoreCategory[]; label: string; active?: boolean; linkClass: string }>();
</script>

<template>
    <div class="group relative">
        <Link :href="route('store.shop')" :class="linkClass" class="flex items-center gap-1.5" aria-haspopup="true" :aria-current="active ? 'page' : undefined">
            {{ label }}
            <ChevronDown class="size-3.5 transition group-focus-within:rotate-180 group-hover:rotate-180 motion-reduce:transition-none" aria-hidden="true" />
        </Link>

        <div
            class="invisible absolute start-1/2 top-full z-50 w-[min(46rem,calc(100vw-3rem))] translate-y-2 border-t-2 border-store bg-(--shell) p-6 opacity-0 shadow-[0_24px_48px_-20px_rgb(42_21_26/0.35)] transition duration-200 ltr:-translate-x-1/2 rtl:translate-x-1/2 group-focus-within:visible group-focus-within:translate-y-0 group-focus-within:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none"
        >
            <ul class="grid grid-cols-3 gap-4 sm:grid-cols-4">
                <li v-for="category in categories.slice(0, 8)" :key="category.id">
                    <Link :href="category.url" class="group/item block text-center">
                        <span class="block aspect-square overflow-hidden bg-(--sand)">
                            <img v-if="category.image" :src="category.image" alt="" loading="lazy" class="size-full object-cover transition duration-500 group-hover/item:scale-105 motion-reduce:transition-none" />
                            <InitialTile v-else :name="category.name" class="text-2xl" />
                        </span>
                        <span class="mt-2 block truncate text-sm font-semibold group-hover/item:text-store-600">{{ category.name }}</span>
                    </Link>
                </li>
            </ul>
            <Link :href="route('store.shop')" class="mt-5 flex items-center justify-center rounded-full bg-(--night) py-3 text-[0.8125rem] font-semibold ltr:tracking-[0.1em] text-white ltr:uppercase hover:bg-(--gold) hover:text-(--night)">
                {{ t('View all products') }}
            </Link>
        </div>
    </div>
</template>
