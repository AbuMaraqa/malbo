<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { Search } from '@/lib/icons';
import { route } from '@/lib/route';

import ProductGrid from './ProductGrid.vue';
import ShopPagination from './ShopPagination.vue';
import { btnOutline, chip, chipActive } from './styles';
import type { ShopPage, StoreCategory } from './types';

/** The grid of the shop and of a category: chips, search, sort, cards, pagination, empty state. */
const props = defineProps<{
    title: string;
    category: { id: number; slug: string; name: string } | null;
    categories: StoreCategory[];
    filters: { q: string; sort: string };
    products: ShopPage;
}>();

const q = ref(props.filters.q);
const sort = ref(props.filters.sort);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;

function reload(): void {
    const query = { ...(q.value ? { q: q.value } : {}), ...(sort.value !== 'newest' ? { sort: sort.value } : {}) };

    router.get(props.category ? route('store.category', { slug: props.category.slug }) : route('store.shop'), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['products', 'filters', 'seo'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

watch(q, () => {
    clearTimeout(timer);
    timer = setTimeout(reload, 400);
});

watch(sort, reload);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div class="mx-auto max-w-310 px-4 pt-10">
        <h1 class="text-3xl font-bold sm:text-4xl">{{ title }}</h1>

        <nav v-if="categories.length" :aria-label="t('Categories')" class="-mx-4 mt-6 overflow-x-auto px-4">
            <ul class="flex gap-2 pb-1">
                <li>
                    <Link :href="route('store.shop')" :class="!category ? chipActive : chip" :aria-current="!category ? 'page' : undefined">{{ t('All') }}</Link>
                </li>
                <li v-for="root in categories" :key="root.id">
                    <Link :href="root.url" :class="category?.id === root.id ? chipActive : chip" :aria-current="category?.id === root.id ? 'page' : undefined">{{ root.name }}</Link>
                </li>
            </ul>
        </nav>

        <div class="mt-6 flex flex-wrap items-end gap-3">
            <label class="flex h-11 min-w-0 flex-[1_1_260px] items-center gap-2 rounded-md border border-(--ink-18) bg-white px-3">
                <Search class="size-4.5 shrink-0" aria-hidden="true" />
                <span class="sr-only">{{ t('Search') }}</span>
                <input v-model="q" type="search" :placeholder="t('Search products')" class="w-full bg-transparent outline-none placeholder:text-muted" />
            </label>
            <label class="flex items-center gap-2 text-sm">
                <span class="text-muted">{{ t('Sort by') }}</span>
                <select v-model="sort" class="h-11 rounded-md border border-(--ink-18) bg-white px-3 text-[15px]">
                    <option value="newest">{{ t('Newest') }}</option>
                    <option value="featured">{{ t('Featured') }}</option>
                    <option value="price_asc">{{ t('Price: low to high') }}</option>
                    <option value="price_desc">{{ t('Price: high to low') }}</option>
                </select>
            </label>
        </div>

        <div class="transition-opacity" :class="{ 'opacity-60': loading }">
            <div v-if="products.data.length === 0" class="mt-10 rounded-lg border border-line bg-white px-6 py-14 text-center">
                <p class="text-lg font-bold">
                    <template v-if="products.total > 0">{{ t('No products on this page.') }}</template>
                    <template v-else-if="filters.q">{{ t('No products match “:q”.', { q: filters.q }) }}</template>
                    <template v-else>{{ t('No products here yet.') }}</template>
                </p>
                <Link v-if="filters.q || category || products.total > 0" :href="route('store.shop')" class="mt-5" :class="btnOutline">{{ t('See all products') }}</Link>
            </div>
            <template v-else>
                <p class="mt-6 text-sm text-muted">{{ products.total_label }}</p>
                <ProductGrid :cards="products.data" class="mt-4" />
                <ShopPagination :products="products" />
            </template>
        </div>
    </div>
</template>
