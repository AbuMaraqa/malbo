<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { ArrowUp, Boxes, Heart, Menu, Search, X } from '@/lib/icons';
import { route } from '@/lib/route';

import AccountMenu from './AccountMenu.vue';
import CartButton from './CartButton.vue';
import CategoryMenu from './CategoryMenu.vue';
import LanguageMenu from './LanguageMenu.vue';
import MobileMenu from './MobileMenu.vue';
import SearchCombobox from './SearchCombobox.vue';
import StoreBrand from './StoreBrand.vue';
import { container, panelFirm } from './styles';
import type { StoreLayoutProps } from './types';

/**
 * The storefront's header in one row: the logo, the pages in the middle ("Shop" opens the category
 * mega menu) and the visitor's icons. The search icon opens a search bar under the header. Phones
 * get the pages in a sheet. It also holds the "back to top" button, since it already follows the
 * scroll.
 */
const props = defineProps<{ store: StoreLayoutProps }>();

const page = usePage();
const scrolled = ref(false);
const farDown = ref(false);
const menuOpen = ref(false);
const searchOpen = ref(false);
const searchPanel = ref<HTMLElement | null>(null);
let stopListening: (() => void) | undefined;

function onScroll(): void {
    scrolled.value = window.scrollY > 8;
    farDown.value = window.scrollY > 600;
}

function toTop(): void {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    stopListening = router.on('navigate', () => {
        menuOpen.value = false;
        searchOpen.value = false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    stopListening?.();
});

// The search bar takes the focus as it opens.
watch(searchOpen, async (open) => {
    if (!open) return;
    await nextTick();
    searchPanel.value?.querySelector<HTMLInputElement>('input')?.focus();
});

// The open sheet keeps the page behind it still.
watch(menuOpen, (open) => document.body.classList.toggle('overflow-hidden', open));

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

    if (props.store.categories_url) {
        links.push({ label: t('Categories'), href: props.store.categories_url, active: component.value === 'Store/Storefront/Categories' });
    }

    if (props.store.about_url) {
        links.push({ label: t('About us'), href: props.store.about_url, active: isAbout.value });
    }

    links.push({ label: t('Track your order'), href: route('store.track'), active: component.value === 'Store/Storefront/TrackOrder' });
    links.push({ label: t('Contact us'), href: route('store.contact'), active: component.value === 'Store/Storefront/Contact' });

    return links;
});

const initialSearch = computed(() => {
    const filters = page.props.filters as { q?: string } | undefined;

    return filters?.q ?? '';
});

const wishlistUrl = computed(() => (props.store.account.signed_in ? props.store.account.wishlist_url : props.store.account.login_url));
// The portal is a storefront of its own (its texts and routes come with its pages): a full page load.
const wholesale = computed(() => {
    const links = props.store.wholesale;

    if (!links) return null;

    return links.merchant
        ? { label: t('Wholesale portal'), href: links.portal_url }
        : { label: t('Wholesale buying'), href: links.apply_url };
});
const inPortal = computed(() => component.value.startsWith('Wholesale/'));
const iconButton = 'relative grid size-11 place-items-center rounded-full transition-colors hover:bg-(--sand) hover:text-store-600';
const navLink =
    'relative py-7 text-[0.8125rem] font-semibold ltr:tracking-[0.08em] whitespace-nowrap ltr:uppercase transition-colors after:absolute after:inset-x-0 after:bottom-5 after:h-0.5 after:origin-center after:scale-x-0 after:bg-(--gold) after:transition-transform hover:text-store-600 hover:after:scale-x-100 aria-[current=page]:text-store-600 aria-[current=page]:after:scale-x-100';
// "Shop" carries the category mega menu.
const shopHref = route('store.shop');
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-line bg-(--paper) transition-shadow" :class="{ [panelFirm]: scrolled }">
        <div :class="container" class="relative flex h-18 items-center gap-3 lg:h-20 lg:gap-8">
            <button type="button" :class="iconButton" class="-ms-2 cursor-pointer lg:hidden" :aria-label="t('Open menu')" :aria-expanded="menuOpen" @click="menuOpen = true">
                <Menu class="size-6" aria-hidden="true" />
            </button>

            <!-- Centered on phones, as in the brand's app; at the start beside the pages on wide screens. -->
            <StoreBrand :name="store.name" :logo="store.logo" class="text-xl max-lg:absolute max-lg:inset-x-0 max-lg:mx-auto max-lg:w-max sm:text-2xl" />

            <nav :aria-label="t('Main')" class="mx-auto hidden items-center gap-7 lg:flex xl:gap-9">
                <template v-for="link in nav" :key="link.href">
                    <CategoryMenu
                        v-if="link.href === shopHref && store.categories.length"
                        :categories="store.categories"
                        :label="link.label"
                        :active="link.active"
                        :link-class="navLink"
                    />
                    <Link v-else :href="link.href" :class="navLink" :aria-current="link.active ? 'page' : undefined">{{ link.label }}</Link>
                </template>
            </nav>

            <div class="ms-auto flex items-center gap-0.5 lg:ms-0">
                <a
                    v-if="wholesale"
                    :href="wholesale.href"
                    data-wholesale-link
                    class="me-2 hidden items-center gap-2 rounded-full border px-3.5 py-2 text-xs font-semibold ltr:tracking-[0.06em] ltr:uppercase transition-colors xl:flex"
                    :class="inPortal ? 'border-store bg-store text-store-contrast' : 'border-store/40 text-store-600 hover:bg-store hover:text-store-contrast'"
                    :aria-current="inPortal ? 'page' : undefined"
                >
                    <Boxes class="size-4" aria-hidden="true" />
                    {{ wholesale.label }}
                </a>
                <button
                    type="button"
                    :class="iconButton"
                    class="cursor-pointer"
                    :aria-label="searchOpen ? t('Close search') : t('Search')"
                    :aria-expanded="searchOpen"
                    aria-controls="store-search"
                    @click="searchOpen = !searchOpen"
                >
                    <X v-if="searchOpen" class="size-5.5" aria-hidden="true" />
                    <Search v-else class="size-5.5" aria-hidden="true" />
                </button>
                <!-- Phones reach the account and the wishlist from the tab bar. -->
                <AccountMenu :account="store.account" class="max-sm:hidden" />
                <Link :href="wishlistUrl" :aria-label="t('My wishlist')" :title="t('My wishlist')" :class="iconButton" class="max-sm:hidden">
                    <Heart class="size-5.5" aria-hidden="true" />
                    <span
                        v-if="store.wishlist.length"
                        aria-hidden="true"
                        class="absolute -end-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-(--gold) px-1 text-[0.6875rem] leading-none font-bold text-(--night)"
                    >
                        {{ store.wishlist.length > 99 ? '99+' : store.wishlist.length }}
                    </span>
                </Link>
                <CartButton :count="store.cart_count" :url="store.cart_url" />
                <LanguageMenu :languages="store.languages" class="ms-2 hidden border-s border-line ps-2 lg:block" />
            </div>
        </div>

        <!-- The search bar, under the header while the search icon has it open. -->
        <div
            v-show="searchOpen"
            id="store-search"
            ref="searchPanel"
            class="absolute inset-x-0 top-full border-b border-line bg-(--paper) py-5 shadow-[0_18px_30px_-24px_rgb(42_21_26/0.4)]"
            @keydown.esc="searchOpen = false"
        >
            <div :class="container">
                <SearchCombobox :initial="initialSearch" class="mx-auto max-w-2xl" />
            </div>
        </div>

        <MobileMenu :open="menuOpen" :store="store" :links="nav" :search="initialSearch" :wholesale="wholesale" @close="menuOpen = false" />

        <button
            type="button"
            :aria-label="t('Back to top')"
            class="fixed start-5 bottom-5 z-30 grid size-11 cursor-pointer place-items-center rounded-full bg-store text-store-contrast shadow-lg transition duration-300 hover:bg-(--gold) hover:text-(--night) motion-reduce:transition-none max-sm:bottom-22"
            :class="[farDown ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0', { 'max-lg:hidden': component === 'Store/Storefront/ProductPage' }]"
            :tabindex="farDown ? 0 : -1"
            @click="toTop"
        >
            <ArrowUp class="size-5" aria-hidden="true" />
        </button>
    </header>
</template>
