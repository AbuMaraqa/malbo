<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import { pageLink, pageLinkActive } from './styles';
import type { ShopPage } from './types';

/** Real links, so search engines can follow every page of the grid. */
defineProps<{ products: ShopPage }>();
</script>

<template>
    <nav v-if="products.last_page > 1" :aria-label="t('Pagination')" class="mt-12 flex flex-wrap items-center justify-center gap-2">
        <Link v-if="products.prev_url" :href="products.prev_url" rel="prev" :class="pageLink">{{ t('Previous') }}</Link>
        <template v-for="(page, index) in products.pages" :key="index">
            <span v-if="page.url === null" class="px-2 text-muted">{{ page.label }}</span>
            <span v-else-if="page.active" :class="pageLinkActive" aria-current="page">{{ page.label }}</span>
            <Link v-else :href="page.url" :class="pageLink">{{ page.label }}</Link>
        </template>
        <Link v-if="products.next_url" :href="products.next_url" rel="next" :class="pageLink">{{ t('Next') }}</Link>
    </nav>
</template>
