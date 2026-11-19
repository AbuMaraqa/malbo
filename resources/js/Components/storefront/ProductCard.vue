<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import CardSizes from './CardSizes.vue';
import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import ProductBadges from './ProductBadges.vue';
import type { StoreCard } from './types';
import WishlistButton from './WishlistButton.vue';

/**
 * A product in a grid: the picture on a cream square with its badges and the wishlist heart, a
 * "View product" bar that rises on hover, then the name and price centered under it.
 */
defineProps<{ card: StoreCard }>();
</script>

<template>
    <article class="group relative flex flex-col">
        <div class="relative aspect-square overflow-hidden rounded-2xl bg-(--sand)">
            <img
                v-if="card.image"
                :src="card.image"
                alt=""
                loading="lazy"
                decoding="async"
                class="size-full object-cover transition duration-500 group-hover:scale-[1.06] motion-reduce:transition-none"
            />
            <InitialTile v-else :name="card.name" class="text-5xl" />
            <ProductBadges :badges="card.badges ?? []" :percent-off="card.price.percent_off" class="absolute inset-x-3 top-3 pe-12" />
            <div class="absolute end-3 top-3">
                <WishlistButton :product-id="card.id" />
            </div>
            <span
                aria-hidden="true"
                class="absolute inset-x-0 bottom-0 hidden translate-y-full bg-store py-3 text-center text-[0.8125rem] font-semibold ltr:tracking-[0.1em] text-store-contrast ltr:uppercase transition duration-300 group-hover:translate-y-0 motion-reduce:transition-none lg:block"
            >
                {{ t('View product') }}
            </span>
        </div>
        <div class="mt-4 flex flex-col items-center gap-1.5 text-center">
            <h3 class="line-clamp-2 text-[0.9375rem] leading-snug transition-colors group-hover:text-store-600">
                <Link :href="card.url" class="after:absolute after:inset-0">{{ card.name }}</Link>
            </h3>
            <PriceRow :price="card.price" class="justify-center" />
            <CardSizes v-if="card.sizes?.length" :sizes="card.sizes" class="mt-0.5 justify-center" />
        </div>
    </article>
</template>
