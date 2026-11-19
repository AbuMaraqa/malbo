<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import ConsentBanner from '@/Components/storefront/ConsentBanner.vue';
import MobileTabBar from '@/Components/storefront/MobileTabBar.vue';
import StorefrontAnnouncement from '@/Components/storefront/StorefrontAnnouncement.vue';
import StorefrontFooter from '@/Components/storefront/StorefrontFooter.vue';
import StorefrontHeader from '@/Components/storefront/StorefrontHeader.vue';
import StorefrontSeo from '@/Components/storefront/StorefrontSeo.vue';
import type { StoreLayoutProps } from '@/Components/storefront/types';
import { useReveal } from '@/Components/storefront/useReveal';
import WhatsAppFloat from '@/Components/storefront/WhatsAppFloat.vue';
import { t } from '@/lib/i18n';
import type { MarketingProps } from '@/lib/metaPixel';
import { useMetaPixel } from '@/lib/useMetaPixel';

/**
 * The storefront's frame. It reads the shared `store` and `seo` props, so it stays mounted while
 * the visitor moves between pages. Light only, whatever the visitor's system or the admin chose.
 * The neutrals follow the MALBO guidelines: cream paper (app.css), maroon for the dark surfaces, ink
 * with a touch of maroon for text. Phones get the tab bar at the bottom, except on the product page,
 * where the order bar sits.
 */
const page = usePage();
const store = computed(() => page.props.store as StoreLayoutProps);
// On the product page phones have the sticky order bar instead of the floating button.
const onProductPage = computed(() => page.component === 'Store/Storefront/ProductPage');

// Meta Pixel: after the visitor accepted marketing cookies, storefront pages only (the server sends
// no `marketing` prop elsewhere).
const marketing = computed(() => page.props.marketing as MarketingProps | null | undefined);
useMetaPixel(() => marketing.value);

useReveal();
</script>

<template>
    <div
        :class="{ 'max-sm:pb-16': !onProductPage }"
        class="flex min-h-screen flex-col bg-(--paper) font-sans text-ink antialiased [font-variant-numeric:tabular-nums] [&_:focus-visible]:outline-2 [&_:focus-visible]:outline-offset-2 [&_:focus-visible]:outline-store"
        style="
            --ink-soft: #57454a;
            --ink-30: rgb(42 21 26 / 0.3);
            --ink-18: rgb(42 21 26 / 0.16);
            --danger: #c81e1e;
            --color-ink: #2a151a;
            --color-muted: #7a6560;
            --color-line: #ead9bb;
            color-scheme: light;
        "
    >
        <StorefrontSeo />

        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-(--shell) focus:px-4 focus:py-2">
            {{ t('Skip to content') }}
        </a>

        <StorefrontAnnouncement v-if="store.announcement" :text="store.announcement" />
        <StorefrontHeader :store="store" />

        <main id="main" class="flex-1">
            <slot />
        </main>

        <StorefrontFooter :store="store" />
        <MobileTabBar v-if="!onProductPage" :store="store" />
        <WhatsAppFloat v-if="store.whatsapp" :number="store.whatsapp" :hide-on-phones="onProductPage" />
        <ConsentBanner :marketing="marketing" />
    </div>
</template>

<style>
/* What utilities cannot say: sections that rise in as they scroll into view. */
.reveal-ready {
    opacity: 0;
    transform: translateY(24px);
    transition:
        opacity 700ms cubic-bezier(0.2, 0.7, 0.2, 1),
        transform 700ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.reveal-ready.reveal-late {
    transition-delay: 150ms;
}

.reveal-ready.is-visible {
    opacity: 1;
    transform: none;
}
</style>
