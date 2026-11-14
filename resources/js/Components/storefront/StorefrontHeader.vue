<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { ArrowUp, Boxes, Heart, Menu, Phone, Search } from '@/lib/icons';
import { route } from '@/lib/route';

import AccountMenu from './AccountMenu.vue';
import CartButton from './CartButton.vue';
import CategoryMenu from './CategoryMenu.vue';
import MobileMenu from './MobileMenu.vue';
import SearchCombobox from './SearchCombobox.vue';
import StoreBrand from './StoreBrand.vue';
import { container, panelFirm } from './styles';
import type { StoreLayoutProps } from './types';

/**
 * The storefront's header: logo, search and the visitor's icons on top, then a bar with the
 * category menu and the pages. Phones get the pages in a sheet. It also holds the "back to top"
 * button, since it already follows the scroll.
 */
const props = defineProps<{ store: StoreLayoutProps }>();

const page = usePage();
const scrolled = ref(false);
const farDown = ref(false);
const menuOpen = ref(false);
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
    stopListening = router.on('navigate', () => (menuOpen.value = false));
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    stopListening?.();
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
const phone = computed(() => props.store.contact?.phone ?? (props.store.contact?.whatsapp ? `+${props.store.contact.whatsapp}` : null));
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
</script>

<template>
    <header class="sticky top-0 z-40 bg-white transition-shadow" :class="{ [panelFirm]: scrolled }">
        <div class="border-b border-line">
            <div :class="container" class="flex h-18 items-center gap-3 sm:gap-6">
                <button type="button" :class="iconButton" class="-ms-2 cursor-pointer lg:hidden" :aria-label="t('Open menu')" :aria-expanded="menuOpen" @click="menuOpen = true">
                    <Menu class="size-6" aria-hidden="true" />
                </button>

                <StoreBrand :name="store.name" :logo="store.logo" class="text-xl sm:text-2xl" />

                <SearchCombobox :initial="initialSearch" class="mx-auto hidden max-w-xl md:block" />

                <div class="ms-auto flex items-center gap-0.5 md:ms-0">
                    <Link :href="route('store.shop')" :aria-label="t('Search')" :class="iconButton" class="md:hidden">
                        <Search class="size-5.5" aria-hidden="true" />
                    </Link>
                    <AccountMenu :account="store.account" />
                    <Link :href="wishlistUrl" :aria-label="t('My wishlist')" :title="t('My wishlist')" :class="iconButton" class="max-sm:hidden">
                        <Heart class="size-5.5" aria-hidden="true" />
                        <span
                            v-if="store.wishlist.length"
                            aria-hidden="true"
                            class="absolute -end-0.5 top-0.5 grid h-5 min-w-5 place-items-center rounded-full bg-ink px-1 text-[0.6875rem] leading-none font-bold text-white"
                        >
                            {{ store.wishlist.length > 99 ? '99+' : store.wishlist.length }}
                        </span>
                    </Link>
                    <CartButton :count="store.cart_count" :url="store.cart_url" />
                </div>
            </div>
        </div>

        <div class="hidden border-b border-line lg:block">
            <div :class="container" class="flex items-center gap-8">
                <CategoryMenu v-if="store.categories.length" :categories="store.categories" />

                <nav :aria-label="t('Main')" class="flex items-center gap-7 text-[15px]">
                    <Link
                        v-for="link in nav"
                        :key="link.href"
                        :href="link.href"
                        class="relative py-3.5 font-medium whitespace-nowrap transition-colors after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:origin-center after:scale-x-0 after:bg-store after:transition-transform hover:text-store-600 hover:after:scale-x-100"
                        :class="{ 'text-store-600 after:scale-x-100': link.active }"
                        :aria-current="link.active ? 'page' : undefined"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <div class="ms-auto flex items-center gap-5 text-sm">
                    <a
                        v-if="wholesale"
                        :href="wholesale.href"
                        data-wholesale-link
                        class="flex items-center gap-2 rounded-md border px-3 py-1.5 font-semibold transition-colors"
                        :class="inPortal ? 'border-store bg-store text-store-contrast' : 'border-store/40 text-store-600 hover:bg-store hover:text-store-contrast'"
                        :aria-current="inPortal ? 'page' : undefined"
                    >
                        <Boxes class="size-4.5" aria-hidden="true" />
                        {{ wholesale.label }}
                    </a>
                    <a v-if="phone" :href="`tel:${phone}`" class="flex items-center gap-2 font-medium hover:text-store-600">
                        <Phone class="size-4.5 text-store-600" aria-hidden="true" />
                        <span dir="ltr">{{ phone }}</span>
                    </a>
                    <!-- Another language is a full page load: its texts come with the page. -->
                    <a :href="store.other_locale_url" :hreflang="store.other_locale" :lang="store.other_locale" class="font-medium hover:text-store-600">
                        {{ store.other_locale_name }}
                    </a>
                </div>
            </div>
        </div>

        <MobileMenu :open="menuOpen" :store="store" :links="nav" :search="initialSearch" :wholesale="wholesale" @close="menuOpen = false" />

        <button
            type="button"
            :aria-label="t('Back to top')"
            class="fixed start-5 bottom-5 z-30 grid size-11 cursor-pointer place-items-center rounded-full bg-ink text-white shadow-lg transition duration-300 hover:bg-store hover:text-store-contrast motion-reduce:transition-none"
            :class="[farDown ? 'translate-y-0 opacity-100' : 'pointer-events-none translate-y-4 opacity-0', { 'max-lg:hidden': component === 'Store/Storefront/ProductPage' }]"
            :tabindex="farDown ? 0 : -1"
            @click="toTop"
        >
            <ArrowUp class="size-5" aria-hidden="true" />
        </button>
    </header>
</template>
