<script setup lang="ts">
import { t } from '@/lib/i18n';

import type { CheckoutQuote } from './types';

/** How far the order is from free delivery, or that it has it. */
defineProps<{ quote: CheckoutQuote }>();
</script>

<template>
    <div v-if="quote.free_shipping_threshold_label || quote.free_shipping" class="rounded-md bg-(--sand-light) p-4 text-sm">
        <p v-if="quote.free_shipping" class="font-bold text-store-700">{{ t('Your order gets free delivery.') }}</p>
        <template v-else>
            <p>{{ t('Add :amount more for free delivery.', { amount: quote.free_shipping_remaining_label ?? '' }) }}</p>
            <div
                class="mt-2 h-2 overflow-hidden rounded-full bg-white"
                role="progressbar"
                :aria-valuenow="quote.free_shipping_progress ?? 0"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="t('Progress to free delivery')"
            >
                <div class="h-full rounded-full bg-store transition-[width]" :style="{ width: `${quote.free_shipping_progress ?? 0}%` }"></div>
            </div>
        </template>
    </div>
</template>
