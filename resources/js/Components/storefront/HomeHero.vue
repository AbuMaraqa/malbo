<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import HeroControls from './hero/HeroControls.vue';
import HeroSlideArch, { type ArchSlide } from './hero/HeroSlideArch.vue';
import HeroSlideCover from './hero/HeroSlideCover.vue';
import { useCarousel } from './hero/useCarousel';
import type { HeroSlideCard, StoreCard } from './types';
import { prefersLessMotion } from './useReveal';

/**
 * The home's banner slider. The slides staff set up in the admin (Home slider) fill the whole
 * banner; without any, the store's own slide (its hero texts and photo) and up to two of its picks
 * stand on the maroon band with the MALBO pattern. Slides cross-fade with a slow zoom and their
 * words rise in; a bar shows each slide's time. They stop while the pointer or the focus is in the
 * banner, follow swipes and arrow keys, and never move by themselves for visitors who ask for less
 * motion.
 */
const props = defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    pieces: StoreCard[];
    slides?: HeroSlideCard[];
}>();

const INTERVAL = 7000;

const covers = computed(() => props.slides ?? []);
const useCovers = computed(() => covers.value.length > 0);

const arches = computed<ArchSlide[]>(() => [
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

const count = computed(() => (useCovers.value ? covers.value.length : arches.value.length));

// Phones stack the arch slide's photo above the words: the banner grows only when a slide has one.
const archHasPhoto = computed(() => arches.value.some((slide) => slide.image !== null));
const height = computed(() => {
    if (useCovers.value) return 'h-[min(82svh,38rem)] sm:h-[min(78svh,42rem)] lg:h-[min(86svh,46rem)]';

    return `lg:min-h-[min(82svh,44rem)] ${archHasPhoto.value ? 'min-h-[46rem]' : 'min-h-[30rem]'}`;
});

const rtl = computed(() => usePage().props.dir === 'rtl');
const carousel = useCarousel(count, { interval: INTERVAL, autoplay: () => !prefersLessMotion(), rtl: () => rtl.value });
const { current, previous, paused, playing } = carousel;

onMounted(carousel.start);

function state(index: number): string {
    if (index === current.value) return 'is-active z-10 opacity-100';

    return index === previous.value ? 'is-leaving pointer-events-none opacity-0' : 'pointer-events-none opacity-0';
}
</script>

<template>
    <section
        data-section="hero"
        class="relative overflow-hidden bg-(--night) text-white"
        :class="useCovers ? '' : 'bg-malbo-pattern'"
        :aria-roledescription="count > 1 ? t('Carousel') : undefined"
        :aria-label="t('Highlights')"
        @mouseenter="carousel.pause"
        @mouseleave="carousel.resume"
        @focusin="carousel.pause"
        @focusout="carousel.resume"
        @keydown="carousel.onKeydown"
        @touchstart.passive="carousel.onTouchStart"
        @touchend.passive="carousel.onTouchEnd"
    >
        <div class="relative" :class="height">
            <template v-if="useCovers">
                <div
                    v-for="(slide, index) in covers"
                    :key="slide.id"
                    class="hero-slide absolute inset-0 transition-opacity duration-1000 ease-out motion-reduce:transition-none"
                    :class="state(index)"
                    :aria-hidden="index !== current"
                    :inert="index !== current"
                    role="group"
                    :aria-roledescription="count > 1 ? t('Slide') : undefined"
                    :aria-label="count > 1 ? t(':current of :total', { current: index + 1, total: count }) : undefined"
                >
                    <HeroSlideCover :slide="slide" :first="index === 0" />
                </div>
            </template>
            <template v-else>
                <div
                    v-for="(slide, index) in arches"
                    :key="slide.key"
                    class="hero-slide absolute inset-0 transition-opacity duration-1000 ease-out motion-reduce:transition-none"
                    :class="state(index)"
                    :aria-hidden="index !== current"
                    :inert="index !== current"
                    role="group"
                    :aria-roledescription="count > 1 ? t('Slide') : undefined"
                    :aria-label="count > 1 ? t(':current of :total', { current: index + 1, total: count }) : undefined"
                >
                    <HeroSlideArch :slide="slide" :first="index === 0" />
                </div>
            </template>
        </div>

        <HeroControls
            v-if="count > 1"
            :count="count"
            :current="current"
            :interval="INTERVAL"
            :playing="playing"
            :paused="paused"
            @go="carousel.goTo"
            @step="carousel.step"
        />
    </section>
</template>

<style>
/*
 * The slider's motion (HomeHero): the showing slide's picture zooms out slowly (and keeps going while
 * it fades away), its words rise in one after another (--hero-delay), and the current bar fills over
 * the slide's time. Nothing moves for visitors who ask for less motion.
 */
@keyframes hero-zoom {
    from {
        transform: scale(1.12);
    }
    to {
        transform: scale(1);
    }
}

@keyframes hero-rise {
    from {
        opacity: 0;
        transform: translateY(28px);
        filter: blur(6px);
    }
    to {
        opacity: 1;
        transform: none;
        filter: none;
    }
}

@keyframes hero-progress {
    from {
        width: 0;
    }
    to {
        width: 100%;
    }
}

.hero-slide.is-active .hero-zoom,
.hero-slide.is-leaving .hero-zoom {
    animation: hero-zoom 9s cubic-bezier(0.2, 0.6, 0.2, 1) both;
    will-change: transform;
}

.hero-slide.is-active .hero-rise {
    animation: hero-rise 900ms cubic-bezier(0.2, 0.7, 0.2, 1) both;
    animation-delay: var(--hero-delay, 0ms);
}

.hero-progress {
    animation-name: hero-progress;
    animation-timing-function: linear;
    animation-fill-mode: both;
}

@media (prefers-reduced-motion: reduce) {
    .hero-slide.is-active .hero-zoom,
    .hero-slide.is-leaving .hero-zoom,
    .hero-slide.is-active .hero-rise {
        animation: none;
    }
}
</style>
