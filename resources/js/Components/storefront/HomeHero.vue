<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import BrandMark from './BrandMark.vue';
import BrandSparkle from './BrandSparkle.vue';
import { btnBrand, btnLg, btnOutline, container, eyebrow } from './styles';
import type { StoreCard } from './types';

/**
 * The home's banner on MALBO cream. With the store's photo it fills the band behind the text;
 * without one, a pick of the store stands beside the text, or the MALBO mark does.
 */
defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    piece: StoreCard | null;
    hasSignature: boolean;
    hasStory: boolean;
}>();
</script>

<template>
    <section data-section="hero" class="relative overflow-hidden bg-(--sand) bg-brand-pattern-soft">
        <template v-if="hero.image">
            <img :src="hero.image" alt="" fetchpriority="high" class="absolute inset-0 size-full object-cover" />
            <!-- Keeps the text readable on any photo, darker on the text's side. -->
            <div aria-hidden="true" class="absolute inset-0 bg-linear-to-l from-(--sand)/0 via-(--sand)/60 to-(--sand)/95 rtl:bg-linear-to-r"></div>
        </template>

        <div :class="container" class="relative grid min-h-[min(78svh,40rem)] items-center gap-10 py-16 lg:grid-cols-2">
            <div class="reveal max-w-xl">
                <p v-if="hero.eyebrow" :class="eyebrow"><BrandSparkle class="me-2 inline-block h-4 w-3 align-[-3px] text-(--gold)" />{{ hero.eyebrow }}</p>
                <h1 class="mt-4 text-[2.5rem] leading-[1.15] font-bold text-balance sm:text-6xl">{{ hero.title }}</h1>
                <p v-if="hero.subtitle" class="mt-5 text-lg leading-8 text-(--ink-soft)">{{ hero.subtitle }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Link :href="route('store.shop')" :class="[btnBrand, btnLg]">{{ t('Shop now') }} <ArrowMark /></Link>
                    <a v-if="hasSignature" href="#signature" :class="[btnOutline, btnLg]">{{ t('Discover the collection') }}</a>
                    <a v-else-if="hasStory" href="#story" :class="[btnOutline, btnLg]">{{ t('About us') }}</a>
                </div>
            </div>

            <Link v-if="!hero.image && piece" :href="piece.url" data-hero-piece class="reveal reveal-late group relative hidden justify-self-center lg:block">
                <span aria-hidden="true" class="absolute inset-6 -z-0 rounded-full bg-(--sand-deep)"></span>
                <BrandSparkle class="absolute end-4 top-6 z-10 h-12 w-9 text-(--gold)" />
                <BrandSparkle class="absolute -start-2 top-1/3 z-10 h-6 w-4.5 text-store" />
                <span class="relative block size-[26rem] overflow-hidden rounded-full border-8 border-white shadow-xl">
                    <img :src="piece.image ?? undefined" alt="" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                </span>
                <span class="absolute start-0 bottom-8 rounded-lg bg-white px-5 py-3.5 shadow-lg">
                    <span class="block text-[13px] font-semibold text-store-600">{{ t('From the collection') }}</span>
                    <span class="mt-0.5 block max-w-52 truncate font-bold">{{ piece.name }}</span>
                    <span class="mt-0.5 block text-sm font-semibold text-(--ink-soft)">{{ piece.price.label }}</span>
                </span>
            </Link>

            <!-- No photo and no pick yet: the MALBO mark, as on the brand book's cover, fills the other half. -->
            <div v-else-if="!hero.image" aria-hidden="true" class="relative hidden h-96 place-items-center lg:grid">
                <span class="absolute aspect-square h-full rounded-full bg-(--sand-deep)"></span>
                <BrandMark class="relative h-72 text-store" />
                <BrandSparkle class="absolute end-[16%] top-4 h-10 w-7.5 text-(--gold)" />
                <BrandSparkle class="absolute start-[14%] bottom-10 h-6 w-4.5 text-store" />
            </div>
        </div>
    </section>
</template>
