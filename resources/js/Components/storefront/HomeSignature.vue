<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import { btnBrand, btnLg, btnOutline, eyebrow, glassStrong } from './styles';
import type { StoreCard } from './types';

/** The signature piece: the store's first pick, large, with its story beside it. */
defineProps<{ card: StoreCard }>();
</script>

<template>
    <section id="signature" data-section="signature" class="relative mt-28 overflow-hidden bg-(--sand-light) py-20 sm:py-28">
        <div aria-hidden="true" class="blob blob-slow absolute start-[10%] -top-20 size-80 rounded-full bg-store opacity-[0.16] sm:size-112"></div>
        <div class="relative mx-auto grid max-w-310 items-center px-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
            <Link :href="card.url" class="reveal group block aspect-4/5 overflow-hidden rounded-[2rem] bg-(--sand-deep) sm:aspect-5/4 lg:aspect-6/5" tabindex="-1" aria-hidden="true">
                <img v-if="card.image" :src="card.image" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-[1.03] motion-reduce:transition-none" />
                <InitialTile v-else :name="card.name" class="text-8xl" />
            </Link>
            <div class="reveal reveal-late relative z-10 -mt-24 rounded-[2rem] p-7 sm:mx-10 sm:p-11 lg:mx-0 lg:-ms-24 lg:mt-0" :class="glassStrong">
                <p :class="eyebrow">{{ t('Signature piece') }}</p>
                <p v-if="card.category" class="mt-4 text-sm text-muted">{{ card.category }}</p>
                <h2 class="mt-1 text-3xl leading-tight font-bold text-balance sm:text-[2.6rem]">{{ card.name }}</h2>
                <p v-if="card.summary" class="mt-4 text-[17px] leading-8 text-(--ink-soft)">{{ card.summary }}</p>
                <PriceRow :price="card.price" size="large" class="mt-6">
                    <span v-if="card.price.discounted" class="rounded-full bg-store px-2.5 py-1 text-xs font-bold text-store-contrast" dir="ltr">−{{ card.price.percent_off }}%</span>
                </PriceRow>
                <div class="mt-8 flex flex-wrap gap-3">
                    <Link :href="card.url" :class="[btnBrand, btnLg]">{{ t('Discover it') }}</Link>
                    <Link :href="route('store.shop')" :class="[btnOutline, btnLg]">{{ t('View all products') }}</Link>
                </div>
            </div>
        </div>
    </section>
</template>
