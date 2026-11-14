<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import { formatChange } from './reportQuery';

/** One channel's figures beside the other's: its net sales, share and change, then the details. */
const props = defineProps<{
    channel: {
        key: 'b2c' | 'b2b';
        label: string;
        summary: Record<string, string | number>;
        changes: Record<string, number | null>;
        share: { net: number; orders: number };
    };
}>();

const rows = computed(() => [
    { label: t('Orders'), value: props.channel.summary.orders, change: props.channel.changes.orders },
    { label: t('Units sold'), value: props.channel.summary.units, change: props.channel.changes.units },
    { label: t('Average order value'), value: props.channel.summary.aov, change: null },
    { label: t('Gross sales'), value: props.channel.summary.gross, change: props.channel.changes.gross },
    { label: t('Discounts'), value: props.channel.summary.discounts, change: props.channel.changes.discounts },
    { label: t('Returns'), value: props.channel.summary.returns, change: props.channel.changes.returns },
    { label: t('Delivery fees'), value: props.channel.summary.delivery, change: null },
    { label: t('Share of orders'), value: `${props.channel.share.orders}%`, change: null },
]);

const tint = computed(() =>
    props.channel.key === 'b2c'
        ? { badge: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400', bar: 'bg-brand-500' }
        : { badge: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400', bar: 'bg-success-500' },
);
</script>

<template>
    <section class="rounded-xl border border-line bg-card p-5 shadow-card">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">{{ channel.label }}</h2>
                <p class="tabular mt-2 text-3xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ channel.summary.net }}</p>
                <p class="mt-1 text-sm text-muted">{{ t('Net sales') }} · {{ formatChange(channel.changes.net) ?? '—' }}</p>
            </div>
            <span class="tabular rounded-md px-2 py-1 text-sm font-semibold" :class="tint.badge">{{ channel.share.net }}%</span>
        </div>

        <div class="mt-4 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/5" role="presentation">
            <div class="h-full rounded-full" :class="tint.bar" :style="{ width: `${Math.min(100, Math.max(0, channel.share.net))}%` }" />
        </div>

        <dl class="mt-5 divide-y divide-line text-sm">
            <div v-for="row in rows" :key="row.label" class="flex items-center justify-between gap-3 py-2">
                <dt class="text-muted">{{ row.label }}</dt>
                <dd class="tabular flex items-center gap-2 font-medium text-ink">
                    {{ typeof row.value === 'number' ? row.value.toLocaleString('en') : row.value }}
                    <span v-if="row.change !== null && row.change !== undefined" class="text-xs font-normal text-muted">{{ formatChange(row.change) }}</span>
                </dd>
            </div>
        </dl>
    </section>
</template>
