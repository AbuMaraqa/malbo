<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import BrandPetal from './BrandPetal.vue';
import BrandSparkle from './BrandSparkle.vue';
import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import { btnBrand, btnLg, container, eyebrow } from './styles';
import type { StoreCard } from './types';

/** The signature piece: the store's first pick, as a wide banner across the page. */
defineProps<{ card: StoreCard }>();
</script>

<template>
    <section id="signature" data-section="signature" :class="container" class="reveal mt-20">
        <div class="relative grid items-center overflow-hidden rounded-xl bg-(--sand) lg:grid-cols-2">
            <BrandPetal class="absolute -start-24 -bottom-10 w-96 text-store opacity-10" />
            <BrandSparkle class="absolute end-6 top-6 h-8 w-6 text-(--gold) lg:end-[calc(50%+1.5rem)]" />
            <div class="relative px-6 py-12 sm:px-12 lg:py-16">
                <p :class="eyebrow">
                    <template v-if="card.price.discounted && card.price.percent_off">{{ t('Save :percent% today', { percent: card.price.percent_off }) }}</template>
                    <template v-else>{{ t('Signature piece') }}</template>
                </p>
                <h2 class="mt-3 text-3xl leading-tight font-bold text-balance sm:text-[2.6rem]">{{ card.name }}</h2>
                <p v-if="card.summary" class="mt-4 max-w-lg text-[17px] leading-8 text-(--ink-soft)">{{ card.summary }}</p>
                <PriceRow :price="card.price" size="large" class="mt-6" />
                <Link :href="card.url" class="mt-8" :class="[btnBrand, btnLg]">{{ t('Shop now') }} <ArrowMark /></Link>
            </div>
            <Link :href="card.url" class="group relative block aspect-4/3 overflow-hidden lg:aspect-auto lg:h-full" tabindex="-1" aria-hidden="true">
                <img v-if="card.image" :src="card.image" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                <InitialTile v-else :name="card.name" class="text-8xl" />
            </Link>
        </div>
    </section>
</template>
