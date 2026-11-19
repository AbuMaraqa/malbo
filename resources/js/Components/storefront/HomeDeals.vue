<script setup lang="ts">
import { ref } from 'vue';

import { t } from '@/lib/i18n';
import { ChevronLeft, ChevronRight } from '@/lib/icons';

import ProductCard from './ProductCard.vue';
import SectionHeading from './SectionHeading.vue';
import { container } from './styles';
import type { StoreCard } from './types';

/**
 * The pieces on offer today, in a row that scrolls sideways (by touch, or by the two round arrows
 * on wider screens). Each card snaps into place.
 */
defineProps<{ cards: StoreCard[] }>();

const row = ref<HTMLElement | null>(null);

/**
 * One screen of cards towards the line's end (1) or its start (-1), in either direction of text.
 * The row's CSS glides it, unless the visitor asks for less motion (`motion-safe:scroll-smooth`).
 */
function scroll(step: 1 | -1): void {
    const el = row.value;
    if (!el) return;
    const rtl = getComputedStyle(el).direction === 'rtl';

    el.scrollBy({ left: el.clientWidth * step * (rtl ? -1 : 1) });
}
</script>

<template>
    <section data-section="deals" :class="container" class="reveal mt-24">
        <SectionHeading :title="t('Today\'s offers')" :lead="t('Prices that will not wait long.')" />
        <div class="relative mt-10">
            <ul ref="row" class="-mx-2 flex snap-x snap-mandatory overflow-x-auto pb-2 motion-safe:scroll-smooth [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                <li v-for="card in cards" :key="card.id" class="w-1/2 shrink-0 snap-start px-2 sm:w-1/3 lg:w-1/4 lg:px-3">
                    <ProductCard :card="card" />
                </li>
            </ul>
            <template v-if="cards.length > 4">
                <button
                    type="button"
                    class="absolute -start-5 top-[38%] z-10 hidden size-10 cursor-pointer place-items-center rounded-full bg-(--gold) text-(--night) shadow-md transition-colors hover:bg-store hover:text-store-contrast lg:grid"
                    :aria-label="t('Previous')"
                    @click="scroll(-1)"
                >
                    <ChevronRight class="size-5 ltr:rotate-180" aria-hidden="true" />
                </button>
                <button
                    type="button"
                    class="absolute -end-5 top-[38%] z-10 hidden size-10 cursor-pointer place-items-center rounded-full bg-(--gold) text-(--night) shadow-md transition-colors hover:bg-store hover:text-store-contrast lg:grid"
                    :aria-label="t('Next')"
                    @click="scroll(1)"
                >
                    <ChevronLeft class="size-5 ltr:rotate-180" aria-hidden="true" />
                </button>
            </template>
        </div>
    </section>
</template>
