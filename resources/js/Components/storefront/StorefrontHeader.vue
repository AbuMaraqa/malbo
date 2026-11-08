<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';
import { Search } from '@/lib/icons';
import { route } from '@/lib/route';

import StoreBrand from './StoreBrand.vue';
import { glass, glassFirm } from './styles';
import type { StoreLayoutProps } from './types';

const props = defineProps<{ store: StoreLayoutProps }>();

const page = usePage();
const scrolled = ref(false);

function onScroll(): void {
    scrolled.value = window.scrollY > 8;
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

const component = computed(() => page.component);
const pathname = computed(() => page.url.split('?')[0]);
const isAbout = computed(() => {
    const about = props.store.about_url;

    return about !== null && !about.includes('#') && about.endsWith(pathname.value);
});

const nav = computed(() => {
    const links = [
        { label: t('Home'), href: route('home'), active: component.value === 'Store/Storefront/Home' },
        { label: t('Shop'), href: route('store.shop'), active: ['Store/Storefront/Shop', 'Store/Storefront/Category'].includes(component.value) },
    ];

    if (props.store.about_url) {
        links.push({ label: t('About us'), href: props.store.about_url, active: isAbout.value });
    }

    links.push({ label: t('Contact us'), href: route('store.contact'), active: component.value === 'Store/Storefront/Contact' });

    return links;
});

const initialSearch = computed(() => {
    const filters = page.props.filters as { q?: string } | undefined;

    return filters?.q ?? '';
});
</script>

<template>
    <header class="sticky top-0 z-40 px-4 pt-3">
        <div class="mx-auto flex max-w-310 flex-wrap items-center gap-x-6 gap-y-3 rounded-lg px-4 py-3 sm:px-5" :class="scrolled ? glassFirm : glass">
            <StoreBrand :name="store.name" :logo="store.logo" class="text-xl sm:text-2xl" />

            <nav :aria-label="t('Main')" class="order-3 flex w-full gap-5 overflow-x-auto text-[15px] sm:order-none sm:w-auto sm:flex-1">
                <Link
                    v-for="link in nav"
                    :key="link.href"
                    :href="link.href"
                    class="py-1 whitespace-nowrap"
                    :class="{ 'font-bold text-store': link.active }"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </nav>

            <div class="ms-auto flex items-center gap-2">
                <form :action="route('store.shop')" method="get" role="search" class="hidden md:block">
                    <label class="flex h-11 items-center gap-2 rounded-md border border-(--ink-18) bg-white/50 px-3">
                        <Search class="size-4.5 shrink-0" aria-hidden="true" />
                        <span class="sr-only">{{ t('Search') }}</span>
                        <input type="search" name="q" :value="initialSearch" :placeholder="t('Search products')" class="w-40 bg-transparent outline-none placeholder:text-muted" />
                    </label>
                </form>
                <Link :href="route('store.shop')" :aria-label="t('Search')" class="grid size-11 place-items-center rounded-md md:hidden">
                    <Search class="size-5" aria-hidden="true" />
                </Link>
                <!-- Another language is a full page load: its texts come with the page. -->
                <a :href="store.other_locale_url" :hreflang="store.other_locale" :lang="store.other_locale" class="grid h-11 place-items-center px-2 text-sm">
                    {{ store.other_locale_name }}
                </a>
            </div>
        </div>
    </header>
</template>
