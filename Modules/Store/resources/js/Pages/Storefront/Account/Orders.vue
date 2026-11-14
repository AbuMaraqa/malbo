<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';

import AccountShell from '@/Components/storefront/AccountShell.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand, btnOutline } from '@/Components/storefront/styles';
import type { AccountOrderPage } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ orders: AccountOrderPage }>();

/** An order placed as a guest joins the account with its number and the phone it was placed with. */
const claimForm = useForm({ number: '', phone: '' });

function claim(): void {
    claimForm.post(route('store.account.orders.claim'), { preserveScroll: true });
}
</script>

<template>
    <AccountShell :title="t('My orders')" :parents="[{ name: t('My account'), url: route('store.account') }]">
        <div v-if="!orders.data.length" class="rounded-lg border border-line bg-white p-8 text-center">
            <p class="text-lg font-bold">{{ t('No orders yet') }}</p>
            <p class="mt-2 text-(--ink-soft)">{{ t('Your orders will show here once you order while signed in.') }}</p>
            <Link :href="route('store.shop')" class="mt-5" :class="btnBrand">{{ t('Shop now') }}</Link>
        </div>

        <ul v-else class="divide-y divide-line rounded-lg border border-line bg-white">
            <li v-for="order in orders.data" :key="order.number">
                <Link :href="order.url" class="flex flex-wrap items-center justify-between gap-3 p-5 hover:bg-(--sand-light)">
                    <div>
                        <p class="font-bold">
                            {{ t('Order') }} <span dir="ltr">{{ order.number }}</span>
                        </p>
                        <p class="text-sm text-(--ink-soft)">
                            <span dir="ltr">{{ order.placed_at }}</span> · {{ t(':count items', { count: order.items_count }) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ order.status.label }}</span>
                        <span class="font-bold">{{ order.total_label }}</span>
                    </div>
                </Link>
            </li>
        </ul>

        <nav v-if="orders.last_page > 1" :aria-label="t('Pages')" class="flex justify-between gap-3">
            <Link v-if="orders.prev_url" :href="orders.prev_url" :class="btnOutline">{{ t('Previous') }}</Link>
            <span v-else></span>
            <Link v-if="orders.next_url" :href="orders.next_url" :class="btnOutline">{{ t('Next') }}</Link>
        </nav>

        <section class="rounded-lg border border-line bg-white p-6" aria-labelledby="claim-title">
            <h2 id="claim-title" class="text-xl font-bold">{{ t('Add a past order to my account') }}</h2>
            <p class="mt-2 text-(--ink-soft)">{{ t('Ordered before you had an account? Enter its number and the phone you ordered with.') }}</p>
            <form class="mt-5 grid gap-5 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate @submit.prevent="claim">
                <StoreField v-model="claimForm.number" :label="t('Order number')" dir="ltr" required :maxlength="40" :error="claimForm.errors.number" />
                <StoreField v-model="claimForm.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="claimForm.errors.phone" />
                <button type="submit" :class="btnOutline" :disabled="claimForm.processing">{{ t('Add the order') }}</button>
            </form>
        </section>
    </AccountShell>
</template>
