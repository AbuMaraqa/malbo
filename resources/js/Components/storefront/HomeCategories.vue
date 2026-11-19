<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import CategoryIconTiles from './CategoryIconTiles.vue';
import InitialTile from './InitialTile.vue';
import { container } from './styles';
import type { StoreCategory } from './types';

/**
 * The root categories right under the hero. Phones get the MALBO app's illustrated tiles. Wider
 * screens get banners: five or more make a mosaic (two banners, a tall one in the middle, two more);
 * fewer share one row. Each banner shows the category's picture (or its MALBO illustration) beside
 * its name and a "Shop now".
 */
const props = defineProps<{ categories: StoreCategory[] }>();

const MOSAIC = 5;
const shown = computed(() => props.categories.slice(0, MOSAIC));
const mosaic = computed(() => shown.value.length === MOSAIC);
// Where each banner of the mosaic sits on wide screens; the middle one spans both rows.
const places = ['lg:col-start-1 lg:row-start-1', 'lg:col-start-1 lg:row-start-2', 'lg:col-start-2 lg:row-span-2 lg:row-start-1', 'lg:col-start-3 lg:row-start-1', 'lg:col-start-3 lg:row-start-2'];
const columns = computed(() => ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-2 lg:grid-cols-4'][shown.value.length] ?? '');
const tall = (index: number): boolean => mosaic.value && index === 2;
</script>

<template>
    <section data-section="categories" :aria-label="t('Shop by category')" :class="container" class="reveal mt-16">
        <CategoryIconTiles :categories="categories.slice(0, 6)" class="sm:hidden" />
        <ul class="grid gap-5 max-sm:hidden" :class="mosaic ? 'sm:grid-cols-2 lg:grid-cols-3 lg:grid-rows-2' : columns">
            <li v-for="(category, index) in shown" :key="category.id" :class="mosaic ? places[index] : ''">
                <Link
                    :href="category.url"
                    class="group relative flex h-56 overflow-hidden rounded-3xl bg-(--sand) sm:h-64"
                    :class="{ 'lg:h-full lg:min-h-[33.25rem]': tall(index) }"
                >
                    <!-- The tall banner: the picture fills it, the text sits on a maroon shade at the bottom. -->
                    <template v-if="tall(index)">
                        <img v-if="category.image" :src="category.image" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                        <InitialTile v-else :name="category.name" class="absolute inset-0 text-7xl" />
                        <span aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-(--night)/85 via-(--night)/10 to-transparent"></span>
                        <span class="relative mt-auto p-8 text-white">
                            <span class="block text-[0.8125rem] font-semibold ltr:tracking-[0.14em] text-(--gold) ltr:uppercase">{{ t('Collection') }}</span>
                            <span class="mt-1 block text-3xl font-bold ltr:uppercase">{{ category.name }}</span>
                            <span class="mt-5 inline-flex items-center gap-2 rounded-full bg-(--shell) px-6 py-2.5 text-xs font-semibold ltr:tracking-[0.1em] text-ink ltr:uppercase transition-colors group-hover:bg-(--gold)">
                                {{ t('Shop now') }} <ArrowMark />
                            </span>
                        </span>
                    </template>

                    <template v-else>
                        <span class="relative z-10 flex w-1/2 flex-col justify-center ps-7">
                            <span class="block text-[0.8125rem] font-semibold ltr:tracking-[0.14em] text-(--gold-ink) ltr:uppercase">{{ t('Collection') }}</span>
                            <span class="mt-1 block text-2xl leading-tight font-bold ltr:uppercase">{{ category.name }}</span>
                            <span class="mt-5 inline-flex w-max items-center gap-2 rounded-full bg-(--night) px-5 py-2.5 text-xs font-semibold ltr:tracking-[0.1em] text-white ltr:uppercase transition-colors group-hover:bg-(--gold) group-hover:text-(--night)">
                                {{ t('Shop now') }}
                            </span>
                        </span>
                        <span class="absolute inset-y-0 end-0 w-1/2 overflow-hidden">
                            <img v-if="category.image" :src="category.image" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                            <InitialTile v-else :name="category.name" class="text-6xl" />
                        </span>
                    </template>
                </Link>
            </li>
        </ul>
        <p v-if="categories.length > MOSAIC" class="mt-6 text-center max-sm:hidden">
            <Link :href="route('store.categories')" class="inline-flex items-center gap-2 text-[0.8125rem] font-semibold ltr:tracking-[0.1em] ltr:uppercase hover:text-store-600">
                {{ t('All categories') }} <ArrowMark />
            </Link>
        </p>
    </section>
</template>
