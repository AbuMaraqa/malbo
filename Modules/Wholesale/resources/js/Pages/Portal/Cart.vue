<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { btnBrand, btnOutline } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CartGroupCard from './parts/CartGroupCard.vue';
import CartSummaryCard from './parts/CartSummaryCard.vue';
import PortalShell from './parts/PortalShell.vue';
import ReorderNotice from './parts/ReorderNotice.vue';
import type { CartGroup, CartSummary, PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    nav: PortalLink[];
    groups: CartGroup[];
    summary: CartSummary;
    checkoutUrl: string;
    /** Set once, right after a reorder. */
    reorder?: { added: number; skipped: string[] } | null;
}>();

const busy = ref(false);

function remove(group: CartGroup): void {
    busy.value = true;
    router.delete(route('store.wholesale.cart.remove', group.product_id), { preserveScroll: true, onFinish: () => (busy.value = false) });
}
</script>

<template>
    <PortalShell :title="t('Wholesale cart')" :nav="nav" current="cart">
        <ReorderNotice v-if="reorder" :result="reorder" class="mb-6" />
        <div v-if="!groups.length" class="rounded-lg border border-dashed border-line p-12 text-center">
            <p class="text-lg">{{ t('Your cart is empty.') }}</p>
            <Link :href="route('store.wholesale.catalog')" class="mt-6" :class="btnBrand">{{ t('Wholesale catalog') }}</Link>
        </div>

        <div v-else class="grid items-start gap-8 lg:grid-cols-[1fr_22rem]">
            <section class="grid gap-4" :aria-label="t('Items in your cart')">
                <CartGroupCard v-for="group in groups" :key="group.product_id" :group="group" :busy="busy" editable @remove="remove(group)" />
            </section>

            <CartSummaryCard :summary="summary">
                <Link v-if="summary.can_checkout" :href="checkoutUrl" class="w-full" :class="btnBrand">{{ t('Checkout') }}</Link>
                <button v-else type="button" disabled class="w-full" :class="btnBrand">{{ t('Checkout') }}</button>
                <Link :href="route('store.wholesale.catalog')" class="w-full" :class="btnOutline">{{ t('Continue shopping') }}</Link>
            </CartSummaryCard>
        </div>
    </PortalShell>
</template>
