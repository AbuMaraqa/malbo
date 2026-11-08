<script setup lang="ts">
import ShopListing from '@/Components/storefront/ShopListing.vue';
import type { ShopPage, StoreCategory } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

defineProps<{ categories: StoreCategory[]; filters: { q: string; sort: string }; products: ShopPage }>();
</script>

<template>
    <ShopListing :title="t('All products')" :category="null" :categories="categories" :filters="filters" :products="products" />
</template>
