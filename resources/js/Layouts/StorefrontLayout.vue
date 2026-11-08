<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import StorefrontAnnouncement from '@/Components/storefront/StorefrontAnnouncement.vue';
import StorefrontFooter from '@/Components/storefront/StorefrontFooter.vue';
import StorefrontHeader from '@/Components/storefront/StorefrontHeader.vue';
import StorefrontSeo from '@/Components/storefront/StorefrontSeo.vue';
import type { StoreLayoutProps } from '@/Components/storefront/types';
import { useReveal } from '@/Components/storefront/useReveal';
import WhatsAppFloat from '@/Components/storefront/WhatsAppFloat.vue';
import { t } from '@/lib/i18n';

/**
 * The storefront's frame. It reads the shared `store` and `seo` props, so it stays mounted while
 * the visitor moves between pages. Light only, whatever the visitor's system or the admin chose.
 */
const page = usePage();
const store = computed(() => page.props.store as StoreLayoutProps);
// On the product page phones have the sticky order bar instead of the floating button.
const onProductPage = computed(() => page.component === 'Store/Storefront/ProductPage');

useReveal();
</script>

<template>
    <div
        class="min-h-screen bg-(--paper) pb-px font-sans text-ink antialiased [font-variant-numeric:tabular-nums] [&_:focus-visible]:outline-2 [&_:focus-visible]:outline-offset-2 [&_:focus-visible]:outline-store"
        style="
            --paper: #f7f7f5;
            --ink-soft: #3d3c39;
            --ink-30: rgb(24 25 26 / 0.3);
            --ink-18: rgb(24 25 26 / 0.18);
            --sand: #eceae5;
            --sand-light: #efede8;
            --sand-deep: #e2dfd9;
            --gold: #d8c9a3;
            --danger: #b42335;
            --color-ink: #18191a;
            --color-muted: #6b6a66;
            --color-line: #e3e1dc;
            color-scheme: light;
        "
    >
        <StorefrontSeo />

        <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:start-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2">
            {{ t('Skip to content') }}
        </a>

        <StorefrontAnnouncement v-if="store.announcement" :text="store.announcement" />
        <StorefrontHeader :store="store" />

        <main id="main">
            <slot />
        </main>

        <StorefrontFooter :store="store" />
        <WhatsAppFloat v-if="store.whatsapp" :number="store.whatsapp" :hide-on-phones="onProductPage" />
    </div>
</template>

<style>
/* What utilities cannot say: soft shapes that drift behind the glass, sections that rise in. */
@keyframes store-drift {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(0, -22px, 0) scale(1.04);
    }
}

.blob {
    animation: store-drift 16s ease-in-out infinite;
    will-change: transform;
}

.blob-slow {
    animation-duration: 22s;
    animation-delay: -6s;
}

.reveal-ready {
    opacity: 0;
    transform: translateY(28px);
    transition:
        opacity 900ms cubic-bezier(0.2, 0.7, 0.2, 1),
        transform 900ms cubic-bezier(0.2, 0.7, 0.2, 1);
}

.reveal-ready.reveal-late {
    transition-delay: 180ms;
}

.reveal-ready.is-visible {
    opacity: 1;
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .blob {
        animation: none;
    }
}
</style>
