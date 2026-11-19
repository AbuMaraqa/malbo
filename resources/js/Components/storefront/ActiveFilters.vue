<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { X } from '@/lib/icons';

import { formatMoney } from './money';
import type { ShopFacets, ShopFilterState } from './types';

/** The chosen filters as chips above the grid: each one removes itself, and "clear all" removes every one. */
const props = defineProps<{ facets: ShopFacets; filters: ShopFilterState }>();

const emit = defineEmits<{ toggle: [attributeId: number, valueId: number]; price: [min: number | null, max: number | null]; clear: [] }>();

const chosen = computed(() =>
    props.facets.attributes.flatMap((attribute) =>
        attribute.values.filter((value) => value.selected).map((value) => ({ key: `${attribute.id}-${value.id}`, attributeId: attribute.id, valueId: value.id, label: `${attribute.name}: ${value.name}` })),
    ),
);

const price = computed(() => {
    const { price_min: min, price_max: max } = props.filters;

    if (min !== null && max !== null) return t('Price: :min – :max', { min: formatMoney(min), max: formatMoney(max) });
    if (min !== null) return t('Price from :min', { min: formatMoney(min) });
    if (max !== null) return t('Price up to :max', { max: formatMoney(max) });

    return null;
});

const chip = 'inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-full border border-line bg-(--shell) ps-3 pe-2 text-sm transition-colors hover:border-store hover:text-store-600';
</script>

<template>
    <div v-if="chosen.length || price || filters.q" class="mt-4 flex flex-wrap items-center gap-2">
        <button v-for="item in chosen" :key="item.key" type="button" :class="chip" :aria-label="t('Remove filter :label', { label: item.label })" @click="emit('toggle', item.attributeId, item.valueId)">
            {{ item.label }}
            <X class="size-4" aria-hidden="true" />
        </button>
        <button v-if="price" type="button" :class="chip" :aria-label="t('Remove filter :label', { label: price })" @click="emit('price', null, null)">
            <span dir="auto">{{ price }}</span>
            <X class="size-4" aria-hidden="true" />
        </button>
        <button type="button" class="min-h-9 cursor-pointer px-2 text-sm font-semibold text-store-600 underline-offset-4 hover:underline" @click="emit('clear')">
            {{ t('Clear all') }}
        </button>
    </div>
</template>
