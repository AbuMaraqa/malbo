<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { Search } from '@/lib/icons';
import { route } from '@/lib/route';

import ActiveFilters from './ActiveFilters.vue';
import FilterDrawer from './FilterDrawer.vue';
import PageHero from './PageHero.vue';
import ProductGrid from './ProductGrid.vue';
import ShopFilters from './ShopFilters.vue';
import ShopPagination from './ShopPagination.vue';
import { btnOutline, chip, chipActive, container } from './styles';
import type { ShopFacets, ShopFilterState, ShopPage, StoreCategory } from './types';

/**
 * The grid of the shop and of a category: the page band, a sidebar with search, the categories and
 * the filters (a drawer on phones), then sort, the chosen filters, cards, pagination and the empty
 * state. Search, filters and sort live in the URL, so a link shows the same products.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        category: { id: number; slug: string; name: string } | null;
        categories: StoreCategory[];
        filters: Partial<ShopFilterState> & { q: string; sort: string };
        products: ShopPage;
        facets?: ShopFacets | null;
        description?: string | null;
        image?: string | null;
        /** The breadcrumb's steps before this page; a category's default is the shop. */
        parents?: { name: string; url: string }[];
        listUrl?: string;
        defaultSort?: string;
    }>(),
    { facets: null, description: null, image: null, parents: undefined, listUrl: undefined, defaultSort: 'newest' },
);

const trail = computed(() => props.parents ?? (props.category ? [{ name: t('Shop'), url: route('store.shop') }] : []));

const q = ref(props.filters.q);
const sort = ref(props.filters.sort);
const attr = ref<Record<string, number[]>>({ ...(props.filters.attr ?? {}) });
const priceMin = ref<number | null>(props.filters.price_min ?? null);
const priceMax = ref<number | null>(props.filters.price_max ?? null);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let skipSearch = false;
let skipSort = false;

/** A search is sorted by relevance until the shopper picks another order (StorefrontShop). */
const sortDefault = (search: string): string => (search.trim() ? 'relevance' : props.defaultSort);

/** Follows the search to its default order, unless the shopper picked one; without a reload of its own. */
function followDefaultSort(search: string, previous: string): void {
    const next = sortDefault(search);

    if (sort.value === sortDefault(previous) && sort.value !== next) {
        skipSort = true;
        sort.value = next;
    }
}

const state = computed<ShopFilterState>(() => ({ q: q.value, sort: sort.value, attr: attr.value, price_min: priceMin.value, price_max: priceMax.value }));
const hasFacets = computed(() => props.facets !== null && (props.facets.attributes.length > 0 || props.facets.price.min !== null || priceMin.value !== null || priceMax.value !== null));
const chosenCount = computed(() => Object.values(attr.value).reduce((sum, ids) => sum + ids.length, 0) + (priceMin.value !== null || priceMax.value !== null ? 1 : 0));
const filtered = computed(() => Boolean(props.filters.q) || chosenCount.value > 0);
const withSidebar = computed(() => props.categories.length > 0 || hasFacets.value);

// The server's answer is the truth: it drops what it does not accept.
watch(
    () => props.filters,
    (filters) => {
        attr.value = { ...(filters.attr ?? {}) };
        priceMin.value = filters.price_min ?? null;
        priceMax.value = filters.price_max ?? null;
    },
);

function query(): Record<string, unknown> {
    const chosen = Object.fromEntries(
        Object.entries(attr.value)
            .filter(([, ids]) => ids.length > 0)
            .map(([id, ids]) => [id, ids.join(',')]),
    );

    return {
        ...(q.value ? { q: q.value } : {}),
        ...(sort.value !== sortDefault(q.value) ? { sort: sort.value } : {}),
        ...(Object.keys(chosen).length ? { attr: chosen } : {}),
        ...(priceMin.value !== null ? { price_min: priceMin.value } : {}),
        ...(priceMax.value !== null ? { price_max: priceMax.value } : {}),
    };
}

function reload(): void {
    clearTimeout(timer);
    const url = props.listUrl ?? (props.category ? route('store.category', { slug: props.category.slug }) : route('store.shop'));

    router.get(url, query() as never, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['products', 'filters', 'facets', 'seo'],
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
}

function toggle(attributeId: number, valueId: number): void {
    const ids = attr.value[attributeId] ?? [];

    attr.value = { ...attr.value, [attributeId]: ids.includes(valueId) ? ids.filter((id) => id !== valueId) : [...ids, valueId] };
    reload();
}

function setPrice(min: number | null, max: number | null): void {
    priceMin.value = min;
    priceMax.value = max;
    reload();
}

function clearAll(): void {
    // Its search watcher would reload a second time.
    skipSearch = q.value !== '';
    followDefaultSort('', q.value);
    q.value = '';
    attr.value = {};
    priceMin.value = null;
    priceMax.value = null;
    reload();
}

watch(q, (value, previous) => {
    if (skipSearch) {
        skipSearch = false;

        return;
    }

    followDefaultSort(value, previous);
    clearTimeout(timer);
    timer = setTimeout(reload, 400);
});

watch(sort, () => {
    if (skipSort) {
        skipSort = false;

        return;
    }

    reload();
});

onBeforeUnmount(() => clearTimeout(timer));

const sideLink = 'flex items-center justify-between rounded-md px-3 py-2.5 transition-colors hover:bg-(--sand-light) hover:text-store-600';
</script>

<template>
    <PageHero :title="title" :lead="description" :image="image" :parents="trail" />

    <div class="mt-12 grid gap-x-10 gap-y-6" :class="[container, { 'lg:grid-cols-[16rem_minmax(0,1fr)]': withSidebar }]">
        <aside class="space-y-8 max-lg:contents" :class="{ 'lg:hidden': !withSidebar }">
            <label class="flex h-11 items-center gap-2 border border-line bg-(--shell) px-3 transition-colors focus-within:border-store max-lg:order-first" :class="{ 'lg:hidden': !withSidebar }">
                <Search class="size-4.5 shrink-0 text-muted" aria-hidden="true" />
                <span class="sr-only">{{ t('Search') }}</span>
                <input v-model="q" type="search" :placeholder="t('Search products')" class="w-full bg-transparent outline-none placeholder:text-muted" />
            </label>

            <nav v-if="categories.length" :aria-label="t('Categories')" class="max-lg:order-first max-lg:-mx-4 max-lg:overflow-x-auto max-lg:px-4">
                <h2 class="mb-3 border-b-2 border-ink pb-3 text-[0.9375rem] font-bold ltr:tracking-[0.08em] ltr:uppercase max-lg:hidden">{{ t('Categories') }}</h2>
                <ul class="flex gap-2 pb-1 lg:hidden">
                    <li>
                        <Link :href="route('store.shop')" :class="!category ? chipActive : chip" :aria-current="!category ? 'page' : undefined">{{ t('All') }}</Link>
                    </li>
                    <li v-for="root in categories" :key="root.id">
                        <Link :href="root.url" :class="category?.id === root.id ? chipActive : chip" :aria-current="category?.id === root.id ? 'page' : undefined">{{ root.name }}</Link>
                    </li>
                </ul>
                <ul class="-mx-3 hidden lg:block">
                    <li>
                        <Link :href="route('store.shop')" :class="[sideLink, { 'font-semibold text-store-600': !category }]">{{ t('All products') }}</Link>
                    </li>
                    <li v-for="root in categories" :key="root.id">
                        <Link :href="root.url" :class="[sideLink, { 'font-semibold text-store-600': category?.id === root.id }]">{{ root.name }}</Link>
                    </li>
                </ul>
            </nav>

            <section v-if="hasFacets && facets" :aria-label="t('Filters')" class="max-lg:hidden">
                <h2 class="mb-4 border-b-2 border-ink pb-3 text-[0.9375rem] font-bold ltr:tracking-[0.08em] ltr:uppercase">{{ t('Filters') }}</h2>
                <ShopFilters :facets="facets" :filters="state" @toggle="toggle" @price="setPrice" />
            </section>
        </aside>

        <div class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3 border-y border-line py-3">
                <p class="text-sm text-muted" aria-live="polite">{{ products.total_label }}</p>
                <div class="flex flex-wrap items-center gap-3">
                    <FilterDrawer v-if="hasFacets && facets" :total-label="products.total_label" :count="chosenCount" class="lg:hidden">
                        <ShopFilters :facets="facets" :filters="state" @toggle="toggle" @price="setPrice" />
                    </FilterDrawer>
                    <label v-if="!withSidebar" class="flex h-10 items-center gap-2 border border-line bg-(--shell) px-3 max-lg:hidden">
                        <Search class="size-4 shrink-0 text-muted" aria-hidden="true" />
                        <span class="sr-only">{{ t('Search') }}</span>
                        <input v-model="q" type="search" :placeholder="t('Search products')" class="w-44 bg-transparent text-sm outline-none placeholder:text-muted" />
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <span class="font-semibold ltr:tracking-[0.08em] ltr:uppercase">{{ t('Sort by') }}:</span>
                        <select v-model="sort" class="h-10 rounded-full border border-ink/20 bg-(--shell) px-3 text-sm">
                            <option v-if="q.trim() || sort === 'relevance'" value="relevance">{{ t('Most relevant') }}</option>
                            <option value="newest">{{ t('Newest') }}</option>
                            <option value="best_selling">{{ t('Best selling') }}</option>
                            <option value="featured">{{ t('Featured') }}</option>
                            <option value="price_asc">{{ t('Price: low to high') }}</option>
                            <option value="price_desc">{{ t('Price: high to low') }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <ActiveFilters v-if="facets" :facets="facets" :filters="state" @toggle="toggle" @price="setPrice" @clear="clearAll" />

            <div class="transition-opacity" :class="{ 'opacity-60': loading }">
                <div v-if="products.data.length === 0" class="mt-8 border border-dashed border-line px-6 py-16 text-center">
                    <Search class="mx-auto size-10 text-(--ink-30)" aria-hidden="true" />
                    <p class="mt-4 text-lg font-bold">
                        <template v-if="products.total > 0">{{ t('No products on this page.') }}</template>
                        <template v-else-if="chosenCount > 0">{{ t('No products match these filters.') }}</template>
                        <template v-else-if="filters.q">{{ t('No products match “:q”.', { q: filters.q }) }}</template>
                        <template v-else>{{ t('No products here yet.') }}</template>
                    </p>
                    <button v-if="filtered && products.total === 0" type="button" class="mt-5" :class="btnOutline" @click="clearAll">{{ t('Clear all filters') }}</button>
                    <Link v-else-if="category || products.total > 0" :href="route('store.shop')" class="mt-5" :class="btnOutline">{{ t('See all products') }}</Link>
                </div>
                <template v-else>
                    <ProductGrid :cards="products.data" :narrow="withSidebar" class="mt-8" />
                    <ShopPagination :products="products" />
                </template>
            </div>
        </div>
    </div>
</template>
