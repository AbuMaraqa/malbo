<script setup lang="ts">
import FilterFacet from './FilterFacet.vue';
import PriceRangeFilter from './PriceRangeFilter.vue';
import type { ShopFacets, ShopFilterState } from './types';

/** The shop's filters: each filterable attribute, then the price; the sidebar and the phones' drawer both show it. */
defineProps<{ facets: ShopFacets; filters: ShopFilterState }>();

const emit = defineEmits<{ toggle: [attributeId: number, valueId: number]; price: [min: number | null, max: number | null] }>();
</script>

<template>
    <div class="space-y-7">
        <FilterFacet v-for="attribute in facets.attributes" :key="attribute.id" :attribute="attribute" @toggle="(valueId) => emit('toggle', attribute.id, valueId)" />

        <PriceRangeFilter
            v-if="facets.price.min !== null || filters.price_min !== null || filters.price_max !== null"
            :min="filters.price_min"
            :max="filters.price_max"
            :bounds="facets.price"
            @apply="(min, max) => emit('price', min, max)"
        />
    </div>
</template>
