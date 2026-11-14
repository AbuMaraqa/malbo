<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import { btnBrand, btnLg, btnOutline, container, eyebrow } from './styles';
import type { StoreCard } from './types';

/**
 * The home's banner. With the store's photo it fills the band behind the text; without one, a
 * pick of the store stands beside the text on a soft background.
 */
defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    piece: StoreCard | null;
    hasSignature: boolean;
    hasStory: boolean;
}>();
</script>

<template>
    <section data-section="hero" class="relative overflow-hidden bg-(--sand)">
        <template v-if="hero.image">
            <img :src="hero.image" alt="" fetchpriority="high" class="absolute inset-0 size-full object-cover" />
            <!-- Keeps the text readable on any photo, darker on the text's side. -->
            <div aria-hidden="true" class="absolute inset-0 bg-linear-to-l from-white/0 via-white/55 to-white/90 rtl:bg-linear-to-r"></div>
        </template>

        <div :class="container" class="relative grid min-h-[min(78svh,40rem)] items-center gap-10 py-16 lg:grid-cols-2">
            <div class="reveal max-w-xl">
                <p v-if="hero.eyebrow" :class="eyebrow">{{ hero.eyebrow }}</p>
                <h1 class="mt-4 text-[2.5rem] leading-[1.15] font-bold text-balance sm:text-6xl">{{ hero.title }}</h1>
                <p v-if="hero.subtitle" class="mt-5 text-lg leading-8 text-(--ink-soft)">{{ hero.subtitle }}</p>
                <div class="mt-9 flex flex-wrap gap-3">
                    <Link :href="route('store.shop')" :class="[btnBrand, btnLg]">{{ t('Shop now') }} <ArrowMark /></Link>
                    <a v-if="hasSignature" href="#signature" :class="[btnOutline, btnLg]">{{ t('Discover the collection') }}</a>
                    <a v-else-if="hasStory" href="#story" :class="[btnOutline, btnLg]">{{ t('About us') }}</a>
                </div>
            </div>

            <Link v-if="!hero.image && piece" :href="piece.url" data-hero-piece class="reveal reveal-late group relative hidden justify-self-center lg:block">
                <span aria-hidden="true" class="absolute inset-6 -z-0 rounded-full bg-store-100"></span>
                <span class="relative block size-[26rem] overflow-hidden rounded-full border-8 border-white shadow-xl">
                    <img :src="piece.image ?? undefined" alt="" class="size-full object-cover transition duration-700 group-hover:scale-105 motion-reduce:transition-none" />
                </span>
                <span class="absolute start-0 bottom-8 rounded-lg bg-white px-5 py-3.5 shadow-lg">
                    <span class="block text-[13px] font-semibold text-store-600">{{ t('From the collection') }}</span>
                    <span class="mt-0.5 block max-w-52 truncate font-bold">{{ piece.name }}</span>
                    <span class="mt-0.5 block text-sm font-semibold text-(--ink-soft)">{{ piece.price.label }}</span>
                </span>
            </Link>

            <!-- No photo and no pick yet: shapes in the store's color fill the other half. -->
            <div v-else-if="!hero.image" aria-hidden="true" class="relative hidden h-96 lg:block">
                <span class="absolute inset-y-0 start-1/2 aspect-square -translate-x-1/2 rounded-full bg-store-100 rtl:translate-x-1/2"></span>
                <span class="absolute start-[18%] top-6 size-40 rounded-full bg-store opacity-90"></span>
                <span class="absolute end-[14%] bottom-4 size-28 rounded-full border-[14px] border-store-300"></span>
                <span class="absolute end-[30%] top-10 size-10 rounded-full bg-ink"></span>
            </div>
        </div>
    </section>
</template>
