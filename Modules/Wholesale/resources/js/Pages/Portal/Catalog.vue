<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { btnBrand, btnOutline, field, pageLink } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CatalogCard from './parts/CatalogCard.vue';
import PortalShell from './parts/PortalShell.vue';
import type { PortalLink, WholesalePage } from './parts/types';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    nav: PortalLink[];
    shop: string;
    filters: { q: string | null; category: string | null };
    categories: { slug: string; name: string }[];
    products: WholesalePage;
}>();

const q = ref(props.filters.q ?? '');
const category = ref(props.filters.category ?? '');

function search(): void {
    router.get(route('store.wholesale.catalog'), { ...(q.value ? { q: q.value } : {}), ...(category.value ? { category: category.value } : {}) }, { preserveState: true });
}
</script>

<template>
    <PortalShell :title="t('Wholesale catalog')" :nav="nav" current="catalog">
        <form role="search" class="mb-8 flex flex-wrap items-end gap-3" @submit.prevent="search">
            <label class="min-w-56 flex-1">
                <span class="sr-only">{{ t('Search') }}</span>
                <input v-model="q" type="search" :placeholder="t('Search by name or SKU')" :class="field" />
            </label>
            <label class="w-56">
                <span class="sr-only">{{ t('Category') }}</span>
                <select v-model="category" :class="field">
                    <option value="">{{ t('All categories') }}</option>
                    <option v-for="item in categories" :key="item.slug" :value="item.slug">{{ item.name }}</option>
                </select>
            </label>
            <button type="submit" :class="btnBrand">{{ t('Search') }}</button>
        </form>

        <p class="mb-4 text-sm text-muted">{{ t(':count products', { count: products.total }) }}</p>

        <div v-if="!products.data.length" class="rounded-lg border border-dashed border-line p-12 text-center">
            <p>{{ t('No products match your search.') }}</p>
            <Link v-if="filters.q || filters.category" :href="route('store.wholesale.catalog')" class="mt-4" :class="btnOutline">{{ t('Show all') }}</Link>
        </div>

        <div v-else class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <CatalogCard v-for="product in products.data" :key="product.id" :product="product" />
        </div>

        <nav v-if="products.last_page > 1" :aria-label="t('Pagination')" class="mt-10 flex justify-center gap-2">
            <Link v-if="products.prev_url" :href="products.prev_url" rel="prev" :class="pageLink">{{ t('Previous') }}</Link>
            <span class="inline-grid h-10 place-items-center px-3 text-sm text-muted">{{ products.current_page }} / {{ products.last_page }}</span>
            <Link v-if="products.next_url" :href="products.next_url" rel="next" :class="pageLink">{{ t('Next') }}</Link>
        </nav>
    </PortalShell>
</template>
