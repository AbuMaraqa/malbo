<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronLeft, ChevronRight } from '@/lib/icons';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import BrandMark from './BrandMark.vue';
import BrandPetal from './BrandPetal.vue';
import BrandSparkle from './BrandSparkle.vue';
import type { StoreCard } from './types';
import { prefersLessMotion } from './useReveal';

/**
 * The home's banner: a slider on a maroon band with the MALBO pattern, the photo in an arch (a
 * rounded picture above the words on phones). The first slide is the store's own (its texts and
 * photo, or the white MALBO mark without one); then a slide for each store pick
 * that has a photo. Slides fade every few seconds, stop while the pointer or the focus is in the
 * banner, and never move by themselves for visitors who ask for less motion.
 */
const props = defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    pieces: StoreCard[];
}>();

interface Slide {
    key: string;
    lead: string | null;
    title: string;
    note: string | null;
    image: string | null;
    url: string;
    action: string;
}

const slides = computed<Slide[]>(() => [
    { key: 'store', lead: props.hero.eyebrow, title: props.hero.title, note: props.hero.subtitle, image: props.hero.image, url: route('store.shop'), action: t('Shop now') },
    ...props.pieces
        .filter((piece) => piece.image)
        .slice(0, 2)
        .map((piece) => ({
            key: `piece-${piece.id}`,
            lead: t('New collection'),
            title: piece.name,
            note: piece.price.discounted && piece.price.percent_off ? t('Save :percent% today', { percent: piece.price.percent_off }) : piece.price.label,
            image: piece.image,
            url: piece.url,
            action: t('Shop now'),
        })),
]);

// Phones stack the photo above the words: the banner grows only when a slide has one.
const hasPhoto = computed(() => slides.value.some((slide) => slide.image !== null));

const current = ref(0);
const paused = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

function go(step: number): void {
    current.value = (current.value + step + slides.value.length) % slides.value.length;
}

onMounted(() => {
    if (slides.value.length < 2 || prefersLessMotion()) return;
    timer = setInterval(() => {
        if (!paused.value) go(1);
    }, 6500);
});

onBeforeUnmount(() => clearInterval(timer));
</script>

<template>
    <section
        data-section="hero"
        class="relative overflow-hidden bg-(--night) bg-malbo-pattern text-white"
        :aria-roledescription="slides.length > 1 ? t('Carousel') : undefined"
        :aria-label="t('Highlights')"
        @mouseenter="paused = true"
        @mouseleave="paused = false"
        @focusin="paused = true"
        @focusout="paused = false"
    >
        <div class="relative lg:min-h-[min(82svh,44rem)]" :class="hasPhoto ? 'min-h-[46rem]' : 'min-h-[30rem]'">
            <div
                v-for="(slide, index) in slides"
                :key="slide.key"
                class="absolute inset-0 transition-opacity duration-700 motion-reduce:transition-none"
                :class="index === current ? 'z-10 opacity-100' : 'pointer-events-none opacity-0'"
                :aria-hidden="index !== current"
                :inert="index !== current"
                role="group"
                :aria-roledescription="slides.length > 1 ? t('Slide') : undefined"
                :aria-label="slides.length > 1 ? t(':current of :total', { current: index + 1, total: slides.length }) : undefined"
            >
                <!-- A big petal echoes the logo behind the text; the photo stands in an arch at the end (the brand's arched forms). -->
                <BrandPetal aria-hidden="true" class="absolute -start-24 top-1/2 w-[46rem] -translate-y-1/2 text-white opacity-[0.06]" />
                <div class="absolute inset-y-10 end-6 hidden w-[min(calc(50%-3rem),34rem)] overflow-hidden rounded-t-[17rem] rounded-b-[2rem] lg:block">
                    <img v-if="slide.image" :src="slide.image" alt="" :fetchpriority="index === 0 ? 'high' : undefined" class="size-full object-cover" />
                    <div v-else class="grid size-full place-items-center bg-(--night-soft)">
                        <BrandMark tone="white" class="h-[46%]" />
                    </div>
                </div>
                <BrandSparkle aria-hidden="true" class="absolute end-[47%] top-[12%] hidden h-10 w-8 text-(--gold) lg:block" />

                <div class="relative mx-auto flex h-full w-full max-w-310 flex-col justify-center gap-7 px-4 py-10 sm:px-6 lg:flex-row lg:items-center lg:justify-start lg:py-0">
                    <!-- Phones see the photo too, rounded above the words. -->
                    <img
                        v-if="slide.image"
                        :src="slide.image"
                        alt=""
                        :fetchpriority="index === 0 ? 'high' : undefined"
                        class="aspect-[16/10] w-full rounded-[1.75rem] object-cover lg:hidden"
                    />
                    <div class="max-w-2xl border-s-2 border-(--gold) ps-6 sm:ps-10 lg:w-1/2">
                        <p v-if="slide.lead" class="text-3xl leading-snug font-light text-white/90 sm:text-6xl sm:leading-tight">{{ slide.lead }}</p>
                        <component
                            :is="index === 0 ? 'h1' : 'h2'"
                            class="mt-2 text-[2.5rem] leading-[1.3] font-bold text-balance sm:text-7xl ltr:leading-[1.05] ltr:uppercase"
                        >
                            {{ slide.title }}
                        </component>
                        <p v-if="slide.note" class="mt-6 text-base font-semibold text-(--gold) ltr:tracking-[0.12em] ltr:uppercase sm:text-lg">{{ slide.note }}</p>
                        <Link
                            :href="slide.url"
                            class="mt-9 inline-flex min-h-12 items-center gap-2 rounded-full bg-(--cream) px-9 text-[0.875rem] font-semibold ltr:tracking-[0.1em] text-(--night) ltr:uppercase transition-colors hover:bg-(--gold)"
                        >
                            {{ slide.action }} <ArrowMark />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <template v-if="slides.length > 1">
            <div class="absolute inset-x-0 bottom-6 z-20 flex justify-center gap-2.5">
                <button
                    v-for="(slide, index) in slides"
                    :key="slide.key"
                    type="button"
                    class="h-2.5 cursor-pointer rounded-full transition-all"
                    :class="index === current ? 'w-8 bg-(--gold)' : 'w-2.5 bg-white/40 hover:bg-white/70'"
                    :aria-label="t('Go to slide :number', { number: index + 1 })"
                    :aria-current="index === current"
                    @click="current = index"
                ></button>
            </div>
            <button
                type="button"
                class="absolute start-4 top-1/2 z-20 hidden size-11 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-(--gold) hover:text-(--night) lg:grid"
                :aria-label="t('Previous slide')"
                @click="go(-1)"
            >
                <ChevronRight class="size-5 ltr:rotate-180" aria-hidden="true" />
            </button>
            <button
                type="button"
                class="absolute end-4 top-1/2 z-20 hidden size-11 -translate-y-1/2 cursor-pointer place-items-center rounded-full bg-white/10 text-white transition-colors hover:bg-(--gold) hover:text-(--night) lg:grid"
                :aria-label="t('Next slide')"
                @click="go(1)"
            >
                <ChevronLeft class="size-5 ltr:rotate-180" aria-hidden="true" />
            </button>
        </template>
    </section>
</template>
