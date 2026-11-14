<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from '../ArrowMark.vue';
import ProductGrid from '../ProductGrid.vue';
import { linkArrow, sectionTitle } from '../styles';
import type { StoreCard } from '../types';

/** A grid of products: picked ones in their order, or a category's newest (with a link to it). */
defineProps<{ block: { heading: string | null; cards: StoreCard[]; more_url: string | null } }>();
</script>

<template>
    <section>
        <div v-if="block.heading || block.more_url" class="flex flex-wrap items-end justify-between gap-4 border-b border-line pb-4">
            <h2 v-if="block.heading" :class="sectionTitle">{{ block.heading }}</h2>
            <Link v-if="block.more_url" :href="block.more_url" :class="linkArrow">{{ t('See more') }} <ArrowMark /></Link>
        </div>
        <ProductGrid :cards="block.cards" class="mt-8" />
    </section>
</template>
