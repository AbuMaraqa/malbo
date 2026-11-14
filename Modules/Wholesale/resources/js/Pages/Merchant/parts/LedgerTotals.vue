<script setup lang="ts">
import { t } from '@/lib/i18n';

import type { LedgerCredit, LedgerTotals } from './types';

/** The account at a glance: the balance, what was invoiced, paid, credited and adjusted, and the credit. */
defineProps<{ totals: LedgerTotals; credit: LedgerCredit }>();
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <div class="rounded-xl border border-line bg-card p-4 shadow-card">
            <p class="text-sm text-muted">{{ t('Balance due') }}</p>
            <p class="mt-1 text-xl font-semibold" :class="totals.balance > 0 ? 'text-error-600 dark:text-error-400' : 'text-ink'" dir="ltr">{{ totals.balance_label }}</p>
        </div>
        <div class="rounded-xl border border-line bg-card p-4 shadow-card">
            <p class="text-sm text-muted">{{ t('Invoiced') }}</p>
            <p class="mt-1 text-lg font-semibold text-ink" dir="ltr">{{ totals.invoiced_label }}</p>
        </div>
        <div class="rounded-xl border border-line bg-card p-4 shadow-card">
            <p class="text-sm text-muted">{{ t('Paid') }}</p>
            <p class="mt-1 text-lg font-semibold text-success-600 dark:text-success-400" dir="ltr">{{ totals.paid_label }}</p>
        </div>
        <div class="rounded-xl border border-line bg-card p-4 shadow-card">
            <p class="text-sm text-muted">{{ t('Credited') }} · {{ t('Adjusted') }}</p>
            <p class="mt-1 text-lg font-semibold text-ink" dir="ltr">{{ totals.credited_label }} · {{ totals.adjusted_label }}</p>
        </div>
        <div class="rounded-xl border border-line bg-card p-4 shadow-card">
            <p class="text-sm text-muted">{{ t('Credit limit') }}</p>
            <template v-if="credit.enabled">
                <p class="mt-1 text-lg font-semibold text-ink" dir="ltr">{{ credit.limit_label }}</p>
                <p class="text-xs text-muted">{{ t('Open orders :open · room left :room', { open: credit.open_label, room: credit.room_label }) }}</p>
            </template>
            <p v-else class="mt-1 text-sm text-muted">{{ t('No credit: cash on delivery or bank transfer only.') }}</p>
        </div>
    </div>
</template>
