<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import { btnBrand, btnLg, btnOutline, eyebrow, glass } from './styles';
import type { StoreCard } from './types';

/** The store's photo (or soft liquid shapes in its color) under a frosted card. */
defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    piece: StoreCard | null;
    hasSignature: boolean;
    hasStory: boolean;
}>();
</script>

<template>
    <section data-section="hero" class="relative -mt-20 flex items-center overflow-hidden bg-(--sand) pt-32 pb-24 lg:min-h-[min(88svh,54rem)]">
        <img v-if="hero.image" :src="hero.image" alt="" fetchpriority="high" class="absolute inset-0 size-full object-cover" />
        <template v-else>
            <!-- Shapes sit partly behind the card, so its glass has something to frost. -->
            <div aria-hidden="true" class="blob absolute start-[22%] top-20 size-72 rounded-full bg-store opacity-25 sm:size-104"></div>
            <div aria-hidden="true" class="blob blob-slow absolute start-[4%] bottom-8 size-40 rounded-full bg-(--gold) opacity-70 sm:size-56"></div>
            <div aria-hidden="true" class="blob absolute end-[6%] top-40 size-64 rounded-full bg-store-200 opacity-60 max-lg:hidden"></div>
        </template>

        <div class="relative mx-auto grid w-full max-w-310 items-center gap-10 px-4 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)]">
            <div class="reveal max-w-170 rounded-[2rem] p-7 sm:p-12" :class="glass">
                <p v-if="hero.eyebrow" :class="eyebrow">{{ hero.eyebrow }}</p>
                <h1 class="mt-4 text-[2.6rem] leading-[1.12] font-bold text-balance sm:text-6xl lg:text-7xl">{{ hero.title }}</h1>
                <p v-if="hero.subtitle" class="mt-6 max-w-xl text-lg leading-8 text-(--ink-soft) sm:text-xl sm:leading-9">{{ hero.subtitle }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Link :href="route('store.shop')" :class="[btnBrand, btnLg]">{{ t('Shop now') }}</Link>
                    <a v-if="hasSignature" href="#signature" :class="[btnOutline, btnLg]">{{ t('Discover the collection') }}</a>
                    <a v-else-if="hasStory" href="#story" :class="[btnOutline, btnLg]">{{ t('About us') }}</a>
                </div>
            </div>

            <!-- Desktop: another pick floats beside the card (the first has its own section below). -->
            <Link v-if="piece" :href="piece.url" data-hero-piece class="reveal reveal-late group hidden rounded-[2rem] p-3 lg:block lg:max-w-105 lg:justify-self-end" :class="glass">
                <span class="block aspect-4/5 overflow-hidden rounded-3xl bg-(--sand-deep)">
                    <img :src="piece.image ?? undefined" alt="" class="size-full object-cover transition duration-700 group-hover:scale-[1.04] motion-reduce:transition-none" />
                </span>
                <span class="flex items-end justify-between gap-4 px-3 pt-4 pb-2">
                    <span class="min-w-0">
                        <span class="block text-[13px] font-bold text-store-700">{{ t('From the collection') }}</span>
                        <span class="mt-0.5 block truncate text-lg font-bold">{{ piece.name }}</span>
                    </span>
                    <span class="shrink-0 text-lg font-bold">{{ piece.price.label }}</span>
                </span>
            </Link>
        </div>
    </section>
</template>
