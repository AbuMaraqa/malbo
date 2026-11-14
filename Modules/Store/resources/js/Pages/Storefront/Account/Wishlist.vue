<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountShell from '@/Components/storefront/AccountShell.vue';
import ProductGrid from '@/Components/storefront/ProductGrid.vue';
import { btnBrand } from '@/Components/storefront/styles';
import type { StoreCard } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ cards: StoreCard[] }>();
</script>

<template>
    <AccountShell :title="t('My wishlist')" :parents="[{ name: t('My account'), url: route('store.account') }]">
        <div v-if="!cards.length" class="rounded-lg border border-line bg-white p-8 text-center">
            <p class="text-lg font-bold">{{ t('Your wishlist is empty') }}</p>
            <p class="mt-2 text-(--ink-soft)">{{ t('Tap the heart on a product to keep it here.') }}</p>
            <Link :href="route('store.shop')" class="mt-5" :class="btnBrand">{{ t('Shop now') }}</Link>
        </div>
        <ProductGrid v-else :cards="cards" />
    </AccountShell>
</template>
