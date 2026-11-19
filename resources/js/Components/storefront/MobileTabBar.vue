<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { Heart, Home, UserRound, Widgets } from '@/lib/icons';
import { route } from '@/lib/route';

import type { StoreLayoutProps } from './types';

/**
 * The phone's tab bar at the bottom, as in the MALBO app: home, categories, the wishlist (with how
 * many pieces it holds) and the account, the current one in the store's maroon. Wide screens have
 * all of it in the header.
 */
const props = defineProps<{ store: StoreLayoutProps }>();

interface Tab {
    key: string;
    label: string;
    href: string;
    icon: Component;
    active: boolean;
    count?: number;
}

const page = usePage();

const tabs = computed<Tab[]>(() => {
    const component = page.component;
    const account = props.store.account;

    return [
        { key: 'home', label: t('Home'), href: route('home'), icon: Home, active: component === 'Store/Storefront/Home' },
        {
            key: 'categories',
            label: t('Categories'),
            href: props.store.categories_url ?? route('store.shop'),
            icon: Widgets,
            active: ['Store/Storefront/Categories', 'Store/Storefront/Category', 'Store/Storefront/Shop'].includes(component),
        },
        {
            key: 'wishlist',
            label: t('My wishlist'),
            href: account.signed_in ? account.wishlist_url : account.login_url,
            icon: Heart,
            active: component === 'Store/Storefront/Account/Wishlist',
            count: props.store.wishlist.length,
        },
        {
            key: 'account',
            label: t('My account'),
            href: account.signed_in ? account.account_url : account.login_url,
            icon: UserRound,
            active: component.startsWith('Store/Storefront/Account/') && component !== 'Store/Storefront/Account/Wishlist',
        },
    ];
});
</script>

<template>
    <nav
        data-tab-bar
        :aria-label="t('Store tabs')"
        class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-(--shell) pb-[env(safe-area-inset-bottom)] shadow-[0_-8px_24px_-16px_rgb(42_21_26/0.3)] sm:hidden"
    >
        <ul class="grid h-16 grid-cols-4">
            <li v-for="tab in tabs" :key="tab.key">
                <Link
                    :href="tab.href"
                    :data-tab="tab.key"
                    :aria-current="tab.active ? 'page' : undefined"
                    class="relative flex h-full flex-col items-center justify-center gap-1 text-[0.75rem] font-medium text-muted transition-colors aria-[current=page]:font-semibold aria-[current=page]:text-store"
                >
                    <span class="relative">
                        <component :is="tab.icon" class="size-6" aria-hidden="true" />
                        <span
                            v-if="tab.count"
                            aria-hidden="true"
                            class="absolute -end-2.5 -top-1.5 grid h-4.5 min-w-4.5 place-items-center rounded-full bg-(--gold) px-1 text-[0.625rem] leading-none font-bold text-(--night)"
                        >
                            {{ tab.count > 99 ? '99+' : tab.count }}
                        </span>
                    </span>
                    {{ tab.label }}
                </Link>
            </li>
        </ul>
    </nav>
</template>
