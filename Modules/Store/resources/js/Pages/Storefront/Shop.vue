<script setup lang="ts">
import ShopListing from '@/Components/storefront/ShopListing.vue';
import type { ShopFacets, ShopFilterState, ShopPage, StoreCategory } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

defineProps<{ categories: StoreCategory[]; filters: ShopFilterState; facets: ShopFacets; products: ShopPage }>();
</script>

<template>
    <ShopListing :title="t('All products')" :category="null" :categories="categories" :filters="filters" :facets="facets" :products="products" />
</template>
