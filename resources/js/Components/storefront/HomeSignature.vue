<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import BrandPetal from './BrandPetal.vue';
import BrandSparkle from './BrandSparkle.vue';
import InitialTile from './InitialTile.vue';
import PriceRow from './PriceRow.vue';
import { btnDark, btnLg, container, eyebrow } from './styles';
import type { StoreCard } from './types';

/**
 * The signature piece: the store's first pick as a wide cream band, its picture on one side and a
 * big headline (its saving when it is on offer) on the other.
 */
defineProps<{ card: StoreCard }>();
</script>

<template>
    <section id="signature" data-section="signature" class="reveal relative mt-24 overflow-hidden bg-(--sand) bg-malbo-pattern-soft">
        <BrandPetal class="absolute -end-20 -bottom-12 w-96 text-store opacity-10" />
        <div :class="container" class="relative grid items-center gap-10 lg:grid-cols-2">
            <Link :href="card.url" class="group relative block aspect-square overflow-hidden lg:-my-px" tabindex="-1" aria-hidden="true">
                <img v-if="card.image" :src="card.image" alt="" loading="lazy" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                <InitialTile v-else :name="card.name" class="text-8xl" />
            </Link>
            <div class="pb-14 lg:py-16">
                <p :class="eyebrow">
                    <BrandSparkle class="me-2 inline-block h-4 w-3 align-[-3px] text-(--gold)" />
                    <template v-if="card.price.discounted && card.price.percent_off">{{ t('Save :percent% today', { percent: card.price.percent_off }) }}</template>
                    <template v-else>{{ t('Signature piece') }}</template>
                </p>
                <h2 class="mt-4 text-3xl leading-tight font-bold text-balance ltr:uppercase sm:text-5xl">{{ card.name }}</h2>
                <p v-if="card.summary" class="mt-5 max-w-lg text-[1.0625rem] leading-8 text-(--ink-soft)">{{ card.summary }}</p>
                <PriceRow :price="card.price" size="large" class="mt-6" />
                <Link :href="card.url" class="mt-9" :class="[btnDark, btnLg]">{{ t('View more') }} <ArrowMark /></Link>
            </div>
        </div>
    </section>
</template>
