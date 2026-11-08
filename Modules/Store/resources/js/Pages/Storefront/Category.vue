<script setup lang="ts">
import ShopListing from '@/Components/storefront/ShopListing.vue';
import type { ShopPage, StoreCategory } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    category: { id: number; slug: string; name: string };
    categories: StoreCategory[];
    filters: { q: string; sort: string };
    products: ShopPage;
}>();
</script>

<template>
    <!-- The key restarts the search box when the visitor moves to another category. -->
    <ShopListing :key="category.id" :title="category.name" :category="category" :categories="categories" :filters="filters" :products="products" />
</template>
