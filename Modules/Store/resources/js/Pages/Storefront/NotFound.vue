<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { btnBrand, btnOutline } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });
</script>

<template>
    <div data-section="not-found" class="mx-auto max-w-2xl px-4 pt-20 text-center">
        <p class="text-sm font-bold tracking-wide text-store-700">404</p>
        <h1 class="mt-3 text-3xl font-bold sm:text-4xl">{{ t('We could not find this page') }}</h1>
        <p class="mt-4 text-[17px] leading-8 text-(--ink-soft)">{{ t('The link may be old, or the product is no longer available.') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <Link :href="route('store.shop')" :class="btnBrand">{{ t('Browse products') }}</Link>
            <Link :href="route('home')" :class="btnOutline">{{ t('Home') }}</Link>
        </div>
    </div>
</template>
