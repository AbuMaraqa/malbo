<script setup lang="ts">
import ProductCard from './ProductCard.vue';
import type { StoreCard } from './types';

/** Product cards in rows: two on phones, three beside the shop's sidebar (`narrow`), four elsewhere. */
defineProps<{ cards: StoreCard[]; narrow?: boolean }>();
</script>

<template>
    <div class="grid grid-cols-2 gap-x-4 gap-y-10 sm:grid-cols-3 lg:gap-x-6" :class="narrow ? 'xl:grid-cols-3' : 'lg:grid-cols-4'">
        <ProductCard v-for="card in cards" :key="card.id" :card="card" />
    </div>
</template>
