<script setup lang="ts">
import { ArrowDown, ArrowUp } from '@/lib/icons';

/** One number on the dashboard: its icon, the value large, then what it counts. */
defineProps<{
    label: string;
    value: string | number;
    /** A change against the last period, e.g. "+4.2%". */
    change?: string;
    direction?: 'up' | 'down';
    tone?: 'brand' | 'success' | 'warning' | 'info';
}>();

const tones = {
    brand: 'bg-brand-50 text-brand-600 dark:bg-brand-500/15 dark:text-brand-400',
    success: 'bg-success-50 text-success-600 dark:bg-success-500/15 dark:text-success-400',
    warning: 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400',
    info: 'bg-blue-light-50 text-blue-light-600 dark:bg-blue-light-500/15 dark:text-blue-light-400',
} as const;
</script>

<template>
    <div class="relative flex h-full flex-col justify-between gap-6 overflow-hidden rounded-xl border border-line bg-card p-5 shadow-card">
        <!-- A soft grid in the corner, the card's only ornament. -->
        <svg class="pointer-events-none absolute -end-6 -top-6 size-32 text-gray-100 dark:text-white/[0.03]" viewBox="0 0 128 128" aria-hidden="true">
            <defs>
                <pattern id="stat-grid" width="16" height="16" patternUnits="userSpaceOnUse"><path d="M16 0H0V16" fill="none" stroke="currentColor" /></pattern>
                <radialGradient id="stat-fade"><stop offset="0" stop-color="#fff" /><stop offset="1" stop-color="#fff" stop-opacity="0" /></radialGradient>
                <mask id="stat-mask"><rect width="128" height="128" fill="url(#stat-fade)" /></mask>
            </defs>
            <rect width="128" height="128" fill="url(#stat-grid)" mask="url(#stat-mask)" />
        </svg>

        <div class="relative flex items-start justify-between gap-3">
            <span class="flex size-11 items-center justify-center rounded-lg [&>svg]:size-6" :class="tones[tone ?? 'brand']">
                <slot name="icon" />
            </span>
            <span
                v-if="change"
                class="tabular inline-flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs font-medium"
                :class="direction === 'down' ? 'bg-error-50 text-error-600 dark:bg-error-500/10 dark:text-error-400' : 'bg-success-50 text-success-600 dark:bg-success-500/10 dark:text-success-400'"
            >
                <ArrowDown v-if="direction === 'down'" class="size-3" />
                <ArrowUp v-else class="size-3" />
                {{ change }}
            </span>
        </div>

        <div class="relative">
            <p class="tabular text-3xl leading-9 font-semibold tracking-tight text-gray-900 dark:text-white">{{ value }}</p>
            <p class="mt-1 text-sm text-muted">{{ label }}</p>
        </div>
    </div>
</template>
