<script setup lang="ts">
import { card } from '@/Components/storefront/styles';
import { t } from '@/lib/i18n';

import type { CartSummary } from './types';

/** The wholesale order's figures and the minimum it must reach. The actions come in the slot. */
defineProps<{ summary: CartSummary }>();
</script>

<template>
    <aside :class="card" class="p-6 lg:sticky lg:top-36" aria-labelledby="wholesale-summary">
        <h2 id="wholesale-summary" class="text-xl font-bold">{{ t('Order summary') }}</h2>
        <dl class="mt-5 grid gap-2">
            <div class="flex items-center justify-between">
                <dt>{{ t('Pieces') }}</dt>
                <dd class="font-semibold tabular-nums">{{ summary.pieces }}</dd>
            </div>
            <div class="flex items-center justify-between text-lg">
                <dt>{{ t('Subtotal') }}</dt>
                <dd class="font-bold">{{ summary.subtotal_label }}</dd>
            </div>
        </dl>
        <p class="mt-2 text-sm text-muted">{{ t('Delivery is arranged by MALBO.') }}</p>

        <div v-if="summary.minimum_value_label || summary.minimum_pieces" class="mt-4 text-sm text-(--ink-soft)">
            {{ t('Minimum order') }}:
            <span v-if="summary.minimum_value_label">{{ summary.minimum_value_label }}</span>
            <span v-if="summary.minimum_value_label && summary.minimum_pieces"> · </span>
            <span v-if="summary.minimum_pieces">{{ t(':count pieces', { count: summary.minimum_pieces }) }}</span>
        </div>
        <ul v-if="summary.minimum_missing.length" class="mt-3 grid gap-1 text-sm font-bold text-(--danger)">
            <li v-for="message in summary.minimum_missing" :key="message">{{ message }}</li>
        </ul>
        <p v-if="summary.too_large" class="mt-3 text-sm font-bold text-(--danger)">{{ summary.too_large }}</p>
        <p v-else-if="summary.blocked" class="mt-3 text-sm font-bold text-(--danger)">{{ t('Remove or change the items marked above to continue.') }}</p>

        <div class="mt-6 grid gap-3">
            <slot />
        </div>
    </aside>
</template>
