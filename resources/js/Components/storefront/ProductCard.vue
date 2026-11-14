<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { Eye } from '@/lib/icons';

import CardSizes from './CardSizes.vue';
import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import ProductBadges from './ProductBadges.vue';
import type { StoreCard } from './types';
import WishlistButton from './WishlistButton.vue';

defineProps<{ card: StoreCard }>();
</script>

<template>
    <article class="group relative flex flex-col">
        <div class="relative aspect-square overflow-hidden rounded-lg bg-(--sand)">
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

            <div class="absolute end-3 top-3 flex flex-col gap-2">
                <WishlistButton :product-id="card.id" />
                <span
                    aria-hidden="true"
                    class="hidden size-10 -translate-y-2 place-items-center rounded-full bg-white text-ink opacity-0 shadow-sm transition duration-300 group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none lg:grid"
                >
                    <Eye class="size-5" />
                </span>
            </div>

            <span
                aria-hidden="true"
                class="absolute inset-x-3 bottom-3 hidden translate-y-3 rounded-md bg-ink py-2.5 text-center text-sm font-semibold text-white opacity-0 transition duration-300 group-hover:translate-y-0 group-hover:opacity-100 motion-reduce:transition-none lg:block"
            >
                {{ t('View product') }}
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-1.5">
            <span v-if="card.category" class="text-[13px] text-muted">{{ card.category }}</span>
            <h3 class="line-clamp-2 text-base leading-snug font-semibold transition-colors group-hover:text-store-600">
                <Link :href="card.url" class="after:absolute after:inset-0">{{ card.name }}</Link>
            </h3>
            <PriceRow :price="card.price" />
            <CardSizes v-if="card.sizes?.length" :sizes="card.sizes" class="mt-0.5" />
        </div>
    </article>
</template>
