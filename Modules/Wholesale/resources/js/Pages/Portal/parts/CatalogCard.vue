<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { card } from '@/Components/storefront/styles';
import { t } from '@/lib/i18n';

import type { WholesaleCard } from './types';

/** A catalog card: the merchant's price ("from" when sizes differ), the suggested retail price, availability. */
defineProps<{ product: WholesaleCard }>();
</script>

<template>
    <article :class="card" class="flex flex-col overflow-hidden">
        <Link :href="product.url" class="block aspect-[4/5] bg-(--sand)">
            <img v-if="product.image" :src="product.image" :alt="product.name" class="size-full object-cover" loading="lazy" />
        </Link>
        <div class="flex flex-1 flex-col gap-1.5 p-4">
            <h3 class="font-bold">
                <Link :href="product.url" class="hover:text-store-600">{{ product.name }}</Link>
            </h3>
            <p class="text-lg font-bold text-ink">
                <span v-if="product.price_from" class="text-sm font-normal text-muted">{{ t('From') }}</span>
                {{ product.price_label }}
            </p>
            <p class="text-sm text-muted">{{ t('Suggested retail price') }}: {{ product.retail_label }}</p>
            <div class="mt-auto flex flex-wrap gap-2 pt-2 text-xs font-semibold">
                <span :class="product.available ? 'text-store-700' : 'text-(--danger)'">{{ product.available ? t('Available') : t('Currently unavailable') }}</span>
                <span v-if="!product.by_piece" class="text-muted">· {{ t('In size runs') }}</span>
            </div>
        </div>
    </article>
</template>
