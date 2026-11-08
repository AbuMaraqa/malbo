<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ArrowMark from './ArrowMark.vue';
import ProductGrid from './ProductGrid.vue';
import { eyebrow, linkArrow, sectionTitle } from './styles';
import type { StoreCard } from './types';

defineProps<{ cards: StoreCard[] }>();
</script>

<template>
    <section data-section="featured" class="reveal mx-auto mt-24 max-w-310 px-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p :class="eyebrow">{{ t('Curated for you') }}</p>
                <h2 class="mt-2" :class="sectionTitle">{{ t('Store picks') }}</h2>
            </div>
            <Link :href="route('store.shop')" :class="linkArrow">{{ t('View all products') }} <ArrowMark /></Link>
        </div>
        <ProductGrid :cards="cards" class="mt-8" />
    </section>
</template>
