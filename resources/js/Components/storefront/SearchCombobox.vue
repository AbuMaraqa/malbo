<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue';

import { t } from '@/lib/i18n';
import { Search } from '@/lib/icons';
import { route } from '@/lib/route';

import type { SearchSuggestions } from './types';

/**
 * The header's search box with suggestions while typing (ARIA combobox with a listbox of grouped
 * options). Asks the server 250 ms after the last key, from 2 characters, and drops an answer that
 * is no longer wanted: a newer key, Enter, Esc or the box losing focus cancel the wait and the
 * request. ↑/↓ or the pointer move the active option, Enter opens it or the shop's results, Esc
 * closes the list (then clears the box).
 */
const props = withDefaults(defineProps<{ initial?: string; compact?: boolean }>(), { initial: '', compact: false });

interface Option {
    id: string;
    kind: 'product' | 'category' | 'all';
    label: string;
    url: string;
    image?: string | null;
    price?: string;
    was?: string | null;
}

const MIN_LENGTH = 2;
const DELAY = 250;

const uid = useId();
const listId = `${uid}-list`;
const productsHeading = `${uid}-products`;
const categoriesHeading = `${uid}-categories`;
const query = ref(props.initial);
const results = ref<SearchSuggestions>({ products: [], categories: [] });
const open = ref(false);
const focused = ref(false);
const active = ref(-1);
const loading = ref(false);
let timer: ReturnType<typeof setTimeout> | undefined;
let controller: AbortController | undefined;

watch(
    () => props.initial,
    (value) => (query.value = value),
);

const options = computed<Option[]>(() => {
    const term = query.value.trim();

    return [
        ...results.value.products.map((product, index) => ({ id: `${uid}-p${index}`, kind: 'product' as const, label: product.name, url: product.url, image: product.image, price: product.price, was: product.was })),
        ...results.value.categories.map((category, index) => ({ id: `${uid}-c${index}`, kind: 'category' as const, label: category.name, url: category.url })),
        ...(term.length >= MIN_LENGTH ? [{ id: `${uid}-all`, kind: 'all' as const, label: t('See all results for “:q”', { q: term }), url: route('store.shop', { q: term }) }] : []),
    ];
});

const products = computed(() => options.value.filter((option) => option.kind === 'product'));
const categories = computed(() => options.value.filter((option) => option.kind === 'category'));
const seeAll = computed(() => options.value.find((option) => option.kind === 'all') ?? null);
const activeId = computed(() => (open.value && active.value >= 0 ? options.value[active.value]?.id : undefined));
const status = computed(() => {
    if (!open.value || loading.value) return '';
    const count = products.value.length + categories.value.length;

    return count ? t(':count suggestions', { count }) : t('No suggestions');
});

/** Stops waiting: no pending timer, no request whose answer could still open the list. */
function cancel(): void {
    clearTimeout(timer);
    controller?.abort();
    controller = undefined;
    loading.value = false;
}

async function fetchSuggestions(term: string): Promise<void> {
    cancel();
    const request = new AbortController();
    controller = request;
    loading.value = true;

    try {
        const response = await fetch(route('store.search.suggest', { q: term }), { headers: { Accept: 'application/json' }, signal: request.signal });

        if (!response.ok || request.signal.aborted) return;
        const found = (await response.json()) as SearchSuggestions;

        if (request.signal.aborted) return;
        results.value = found;
        active.value = -1;
        // Only for a buyer still in the box: an answer after Enter or a click elsewhere stays shut.
        open.value = focused.value;
    } catch {
        // Aborted, or offline: the box still searches on Enter.
    } finally {
        if (controller === request) {
            loading.value = false;
            controller = undefined;
        }
    }
}

function onInput(): void {
    cancel();
    const term = query.value.trim();

    if (term.length < MIN_LENGTH) {
        results.value = { products: [], categories: [] };
        open.value = false;

        return;
    }

    timer = setTimeout(() => fetchSuggestions(term), DELAY);
}

function move(step: number): void {
    if (!options.value.length) return;
    open.value = true;

    const count = options.value.length;
    active.value = active.value < 0 ? (step > 0 ? 0 : count - 1) : (active.value + step + count) % count;
}

function close(): void {
    cancel();
    open.value = false;
    active.value = -1;
}

function choose(option: Option): void {
    close();
    router.visit(option.url);
}

function submit(): void {
    const option = open.value && active.value >= 0 ? options.value[active.value] : undefined;

    if (option) {
        choose(option);

        return;
    }

    close();
    const term = query.value.trim();
    router.get(route('store.shop'), term ? { q: term } : {});
}

function onEscape(): void {
    if (open.value) {
        close();
    } else {
        query.value = '';
        onInput();
    }
}

function onFocus(): void {
    focused.value = true;
    open.value = options.value.length > 1;
}

function onBlur(): void {
    focused.value = false;
    close();
}

function hover(option: Option): void {
    active.value = options.value.indexOf(option);
}

onBeforeUnmount(cancel);

const optionClass = (option: Option) => [
    'flex cursor-pointer items-center gap-3 rounded-md px-3 py-2',
    option.id === activeId.value ? 'bg-(--sand-light) text-store-600' : '',
];
const heading = 'px-3 pt-2 pb-1.5 text-xs font-semibold tracking-[0.12em] text-muted uppercase';
</script>

<template>
    <form :action="route('store.shop')" method="get" role="search" class="relative w-full" @submit.prevent="submit">
        <label
            class="flex h-11 items-center overflow-hidden rounded-md border border-line bg-(--sand-light) transition-colors focus-within:border-store focus-within:bg-white"
            :class="{ 'gap-2 ps-3': compact }"
        >
            <Search v-if="compact" class="size-4.5 shrink-0 text-muted" aria-hidden="true" />
            <span class="sr-only">{{ t('Search') }}</span>
            <input
                v-model="query"
                type="search"
                name="q"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="open"
                :aria-controls="listId"
                :aria-activedescendant="activeId"
                :placeholder="t('Search products')"
                class="h-full w-full bg-transparent outline-none placeholder:text-muted"
                :class="{ 'px-4': !compact }"
                @input="onInput"
                @keydown.down.prevent="move(1)"
                @keydown.up.prevent="move(-1)"
                @keydown.esc.prevent="onEscape"
                @focus="onFocus"
                @blur="onBlur"
            />
            <button v-if="!compact" type="submit" class="grid h-full w-12 shrink-0 cursor-pointer place-items-center bg-store text-store-contrast hover:bg-store-700" :aria-label="t('Search')">
                <Search class="size-5" aria-hidden="true" />
            </button>
        </label>

        <span class="sr-only" role="status" aria-live="polite">{{ status }}</span>

        <!-- mousedown kept from the input: dragging the scrollbar or pressing an option must not blur and close. -->
        <div
            v-show="open && options.length"
            :id="listId"
            role="listbox"
            :aria-label="t('Suggestions')"
            class="absolute inset-x-0 top-full z-50 mt-1 max-h-[70vh] overflow-y-auto rounded-md border border-line bg-white p-2 text-start shadow-[0_10px_30px_-18px_rgb(22_28_45/0.35)]"
            @mousedown.prevent
        >
            <div v-if="products.length" role="group" :aria-labelledby="productsHeading">
                <div :id="productsHeading" role="presentation" :class="heading">{{ t('Products') }}</div>
                <div
                    v-for="option in products"
                    :id="option.id"
                    :key="option.id"
                    role="option"
                    :aria-selected="option.id === activeId"
                    :class="optionClass(option)"
                    @mouseenter="hover(option)"
                    @click="choose(option)"
                >
                    <img v-if="option.image" :src="option.image" alt="" class="size-11 shrink-0 rounded object-cover" loading="lazy" />
                    <span v-else class="size-11 shrink-0 rounded bg-(--sand)" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                    <span class="shrink-0 text-sm font-semibold whitespace-nowrap text-ink">
                        {{ option.price }}
                        <s v-if="option.was" class="ms-1 font-normal text-muted"><span class="sr-only">{{ t('Was') }} </span>{{ option.was }}</s>
                    </span>
                </div>
            </div>
            <div v-if="categories.length" role="group" :aria-labelledby="categoriesHeading">
                <div :id="categoriesHeading" role="presentation" :class="heading">{{ t('Categories') }}</div>
                <div
                    v-for="option in categories"
                    :id="option.id"
                    :key="option.id"
                    role="option"
                    :aria-selected="option.id === activeId"
                    :class="optionClass(option)"
                    @mouseenter="hover(option)"
                    @click="choose(option)"
                >
                    {{ option.label }}
                </div>
            </div>
            <div
                v-if="seeAll"
                :id="seeAll.id"
                role="option"
                :aria-selected="seeAll.id === activeId"
                :class="optionClass(seeAll)"
                class="mt-1 border-t border-line font-semibold text-store-600"
                @mouseenter="hover(seeAll)"
                @click="choose(seeAll)"
            >
                {{ seeAll.label }}
            </div>
        </div>
    </form>
</template>
