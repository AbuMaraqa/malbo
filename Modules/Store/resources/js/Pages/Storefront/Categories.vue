<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import CategoryTiles from '@/Components/storefront/CategoryTiles.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import { btnOutline, container } from '@/Components/storefront/styles';
import type { StoreCategoryTile } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ categories: StoreCategoryTile[] }>();
</script>

<template>
    <PageHero :title="t('Categories')" />
    <div :class="container" class="mt-12">
        <CategoryTiles v-if="categories.length" :categories="categories" />
        <div v-else class="rounded-lg border border-dashed border-line px-6 py-16 text-center">
            <p class="text-lg font-bold">{{ t('No categories yet.') }}</p>
            <Link :href="route('store.shop')" class="mt-5" :class="btnOutline">{{ t('See all products') }}</Link>
        </div>
    </div>
</template>
