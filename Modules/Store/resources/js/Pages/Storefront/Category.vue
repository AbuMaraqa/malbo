<script setup lang="ts">
import ShopListing from '@/Components/storefront/ShopListing.vue';
import type { ShopFacets, ShopFilterState, ShopPage, StoreCategory } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    category: { id: number; slug: string; name: string; description: string | null; image: string | null };
    categories: StoreCategory[];
    filters: ShopFilterState;
    facets: ShopFacets;
    products: ShopPage;
}>();
</script>

<template>
    <!-- The key restarts the search box when the visitor moves to another category. -->
    <ShopListing
        :key="category.id"
        :title="category.name"
        :description="category.description"
        :image="category.image"
        :category="category"
        :categories="categories"
        :filters="filters"
        :facets="facets"
        :products="products"
    />
</template>
