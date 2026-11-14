<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import ProductGrid from './ProductGrid.vue';
import { container, linkArrow, sectionTitle } from './styles';
import type { StoreCard } from './types';

defineProps<{ cards: StoreCard[] }>();
</script>

<template>
    <section data-section="featured" :class="container" class="reveal mt-20">
        <div class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-4">
            <div>
                <h2 :class="sectionTitle">{{ t('Store picks') }}</h2>
                <p class="mt-1.5 text-[15px] text-muted">{{ t('Curated for you') }}</p>
            </div>
            <Link :href="route('store.shop', { sort: 'featured' })" :class="linkArrow">{{ t('See more') }} <ArrowMark /></Link>
        </div>
        <ProductGrid :cards="cards" class="mt-8" />
    </section>
</template>
