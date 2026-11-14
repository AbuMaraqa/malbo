<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ProductGrid from './ProductGrid.vue';
import SectionHeading from './SectionHeading.vue';
import { btnOutline, container } from './styles';
import type { StoreCard } from './types';

/** The newest products, with a way on to the whole shop. */
defineProps<{ cards: StoreCard[] }>();
</script>

<template>
    <section data-section="arrivals" :class="container" class="reveal mt-20">
        <SectionHeading :title="t('New arrivals')" :lead="t('The latest pieces to reach the store.')" />
        <ProductGrid :cards="cards" class="mt-10" />
        <div class="mt-10 text-center">
            <Link :href="route('store.shop')" :class="btnOutline">{{ t('View all products') }}</Link>
        </div>
    </section>
</template>
