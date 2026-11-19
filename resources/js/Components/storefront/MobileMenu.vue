<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { Boxes, X } from '@/lib/icons';

import SearchCombobox from './SearchCombobox.vue';
import StoreBrand from './StoreBrand.vue';
import type { StoreLayoutProps } from './types';

/** The phones' menu: a sheet from the start edge with search, the pages and the categories. */
defineProps<{
    open: boolean;
    store: StoreLayoutProps;
    links: { label: string; href: string; active: boolean }[];
    search: string;
    /** The header's way into the wholesale portal, or null. */
    wholesale: { label: string; href: string } | null;
}>();

const emit = defineEmits<{ close: [] }>();
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" :aria-label="t('Menu')">
        <button type="button" class="absolute inset-0 cursor-default bg-(--night)/50" :aria-label="t('Close menu')" @click="emit('close')"></button>
        <nav class="absolute inset-y-0 start-0 flex w-[min(22rem,88vw)] flex-col overflow-y-auto bg-(--shell) shadow-2xl" :aria-label="t('Main')">
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                <StoreBrand :name="store.name" :logo="store.logo" class="text-xl" />
                <button type="button" class="grid size-11 cursor-pointer place-items-center rounded-md hover:bg-(--sand)" :aria-label="t('Close menu')" @click="emit('close')">
                    <X class="size-5" aria-hidden="true" />
                </button>
            </div>

            <div class="border-b border-line p-4">
                <SearchCombobox :initial="search" compact />
            </div>

            <ul class="border-b border-line py-2">
                <li v-for="link in links" :key="link.href">
                    <Link
                        :href="link.href"
                        class="block px-5 py-3 font-medium hover:bg-(--sand-light)"
                        :class="{ 'text-store-600': link.active }"
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </li>
            </ul>

            <a
                v-if="wholesale"
                :href="wholesale.href"
                data-wholesale-link
                class="mx-4 mt-4 flex items-center justify-center gap-2 rounded-md border border-store/40 px-4 py-3 font-semibold text-store-600"
            >
                <Boxes class="size-5" aria-hidden="true" />
                {{ wholesale.label }}
            </a>

            <template v-if="store.categories.length">
                <p class="px-5 pt-4 pb-1 text-xs font-semibold ltr:tracking-[0.12em] text-muted ltr:uppercase">{{ t('Categories') }}</p>
                <ul class="pb-4">
                    <li v-for="category in store.categories" :key="category.id">
                        <Link :href="category.url" class="block px-5 py-2.5 text-(--ink-soft) hover:bg-(--sand-light) hover:text-store-600">{{ category.name }}</Link>
                    </li>
                </ul>
            </template>

            <a :href="store.other_locale_url" :hreflang="store.other_locale" :lang="store.other_locale" class="mt-auto border-t border-line px-5 py-4 text-sm font-medium">
                {{ store.other_locale_name }}
            </a>
        </nav>
    </div>
</template>
