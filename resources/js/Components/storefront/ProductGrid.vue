<script setup lang="ts">
import ProductCard from './ProductCard.vue';
import type { StoreCard } from './types';

defineProps<{ cards: StoreCard[] }>();
</script>

<template>
    <div class="grid grid-cols-2 gap-x-4 gap-y-10 lg:grid-cols-4 lg:gap-x-6">
        <ProductCard v-for="card in cards" :key="card.id" :card="card" />
    </div>
</template>
