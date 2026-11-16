<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { t } from '@/lib/i18n';
import { UserRound } from '@/lib/icons';

import type { StoreAccount } from './types';

/**
 * The header's account entry: a menu (account, orders, wishlist, sign out) once signed in, a
 * sign-in link before. A <details> menu: it opens without scripts, so it works before hydration.
 * The header stays mounted between pages, so the menu closes itself on each visit.
 */
defineProps<{ account: StoreAccount }>();

const menu = ref<HTMLDetailsElement | null>(null);
let stopListening: (() => void) | undefined;

onMounted(() => {
    stopListening = router.on('navigate', () => menu.value?.removeAttribute('open'));
});

onBeforeUnmount(() => stopListening?.());
</script>

<template>
    <details v-if="account.signed_in" ref="menu" class="group relative">
        <summary
            class="grid size-11 cursor-pointer list-none place-items-center rounded-full transition-colors hover:bg-(--sand) hover:text-store-600 [&::-webkit-details-marker]:hidden"
            :aria-label="t('My account')"
            :title="account.name ?? t('My account')"
        >
            <UserRound class="size-5.5" aria-hidden="true" />
        </summary>
        <div class="absolute end-0 top-full z-50 mt-2 w-60 rounded-lg border border-line bg-white p-2 text-[15px] shadow-[0_24px_48px_-20px_rgb(42_21_26/0.35)]">
            <p v-if="account.name" class="truncate px-3 pt-1 pb-2 text-sm text-muted">{{ t('Hello, :name', { name: account.name }) }}</p>
            <Link :href="account.account_url" class="block rounded-md px-3 py-2 hover:bg-(--sand-light)">{{ t('My account') }}</Link>
            <Link :href="account.orders_url" class="block rounded-md px-3 py-2 hover:bg-(--sand-light)">{{ t('My orders') }}</Link>
            <Link :href="account.wishlist_url" class="block rounded-md px-3 py-2 hover:bg-(--sand-light)">{{ t('My wishlist') }}</Link>
            <Link :href="account.logout_url" method="post" as="button" class="block w-full cursor-pointer rounded-md px-3 py-2 text-start hover:bg-(--sand-light)">
                {{ t('Sign out') }}
            </Link>
        </div>
    </details>
    <Link v-else :href="account.login_url" :aria-label="t('Sign in')" :title="t('Sign in')" class="grid size-11 place-items-center rounded-full transition-colors hover:bg-(--sand) hover:text-store-600">
        <UserRound class="size-5.5" aria-hidden="true" />
    </Link>
</template>
