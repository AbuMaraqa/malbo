<script setup lang="ts">
import { t } from '@/lib/i18n';

import FreeShippingBar from './FreeShippingBar.vue';
import type { CheckoutLine, CheckoutQuote } from './types';

/** The order as the server priced it: lines, subtotal, discount, delivery and total. */
defineProps<{ lines: CheckoutLine[]; quote: CheckoutQuote; updating: boolean }>();
</script>

<template>
    <aside class="rounded-lg border border-line bg-(--shell) p-6" aria-labelledby="checkout-summary">
        <h2 id="checkout-summary" class="text-xl font-bold">{{ t('Order summary') }}</h2>

        <ul class="mt-4 divide-y divide-line">
            <li v-for="line in lines" :key="line.id" class="flex gap-3 py-3">
                <div class="relative size-16 shrink-0 overflow-hidden rounded-md bg-(--sand)">
                    <img v-if="line.image" :src="line.image" :alt="line.name" class="size-full object-cover" loading="lazy" />
                </div>
                <div class="min-w-0 flex-1 text-sm">
                    <p class="font-bold">{{ line.name }}</p>
                    <p v-if="line.options.length" class="text-(--ink-soft)">{{ line.options.map((option) => (line.bundle ? [option.name, option.value].filter(Boolean).join(': ') : option.value)).join(' · ') }}</p>
                    <p class="text-muted">{{ t('Quantity') }}: {{ line.quantity }}</p>
                </div>
                <p class="text-sm font-bold">{{ line.total_label }}</p>
            </li>
        </ul>

        <dl class="mt-4 space-y-2 border-t border-line pt-4 transition-opacity" :class="{ 'opacity-60': updating }" aria-live="polite">
            <div class="flex justify-between">
                <dt>{{ t('Subtotal') }}</dt>
                <dd>{{ quote.subtotal_label }}</dd>
            </div>
            <div v-if="quote.discount > 0" class="flex justify-between text-store-700">
                <dt>{{ t('Discount') }} <span v-if="quote.coupon" dir="ltr">({{ quote.coupon.code }})</span></dt>
                <dd>−{{ quote.discount_label }}</dd>
            </div>
            <div class="flex justify-between">
                <dt>{{ t('Delivery') }}</dt>
                <dd>{{ quote.delivery_fee_label ?? t('Choose your city') }}</dd>
            </div>
            <div v-if="quote.zone" class="flex justify-between text-sm text-muted">
                <dt>{{ t('Expected delivery') }}</dt>
                <dd>{{ quote.zone.eta }}</dd>
            </div>
            <div class="flex justify-between border-t border-line pt-3 text-lg font-bold">
                <dt>{{ t('Total') }}</dt>
                <dd>{{ quote.total_label }}</dd>
            </div>
        </dl>

        <FreeShippingBar :quote="quote" class="mt-4" />
    </aside>
</template>
