<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PageHero from './PageHero.vue';
import StoreFlash from './StoreFlash.vue';

/** The frame of the account's pages: its title, the account's menu, and the saved message. */
defineProps<{ title: string; parents?: { name: string; url: string }[] }>();

const page = usePage();
const current = computed(() => page.component);

const links = computed(() => [
    { label: t('My details'), href: route('store.account'), active: current.value === 'Store/Storefront/Account/Profile' },
    { label: t('My orders'), href: route('store.account.orders'), active: ['Store/Storefront/Account/Orders', 'Store/Storefront/Account/OrderDetail'].includes(current.value) },
    { label: t('My addresses'), href: route('store.account.addresses'), active: current.value === 'Store/Storefront/Account/Addresses' },
    { label: t('My wishlist'), href: route('store.account.wishlist'), active: current.value === 'Store/Storefront/Account/Wishlist' },
    { label: t('My reviews'), href: route('store.account.reviews'), active: current.value === 'Store/Storefront/Account/Reviews' },
]);
</script>

<template>
    <PageHero :title="title" :parents="parents" />
    <div class="mx-auto w-full max-w-310 px-4 pt-12 sm:px-6">
        <div class="grid items-start gap-8 lg:grid-cols-[16rem_1fr]">
            <nav :aria-label="t('My account')" class="flex gap-1 overflow-x-auto rounded-lg border border-line bg-white p-2 lg:sticky lg:top-36 lg:flex-col">
                <Link
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    class="rounded-md px-4 py-2.5 whitespace-nowrap transition-colors"
                    :class="link.active ? 'bg-store font-semibold text-store-contrast' : 'hover:bg-(--sand-light) hover:text-store-600'"
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
                <Link :href="route('store.account.logout')" method="post" as="button" class="cursor-pointer rounded-md px-4 py-2.5 text-start whitespace-nowrap text-muted hover:bg-(--sand-light) hover:text-(--danger)">
                    {{ t('Sign out') }}
                </Link>
            </nav>

            <div class="grid min-w-0 gap-6">
                <StoreFlash />
                <slot />
            </div>
        </div>
    </div>
</template>
