<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { Package, Phone, Returns } from '@/lib/icons';

import type { Alert } from './types';

/** What needs attention now, each opening the list that shows it. A count above 0 stands out. */
defineProps<{ alerts: Alert[] }>();

const icons = { awaiting_confirmation: Phone, low_stock: Package, open_returns: Returns };
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 md:gap-6">
        <component
            :is="alert.url ? Link : 'div'"
            v-for="alert in alerts"
            :key="alert.key"
            :href="alert.url ?? undefined"
            class="group flex items-center gap-4 rounded-2xl border bg-card p-5 transition-colors"
            :class="alert.value > 0 ? 'border-warning-500/40 hover:bg-warning-50 dark:hover:bg-warning-500/10' : 'border-line hover:bg-gray-50 dark:hover:bg-white/[0.03]'"
        >
            <span
                class="flex size-11 shrink-0 items-center justify-center rounded-xl"
                :class="alert.value > 0 ? 'bg-warning-50 text-warning-600 dark:bg-warning-500/15 dark:text-warning-400' : 'bg-gray-100 text-gray-500 dark:bg-white/5 dark:text-gray-400'"
            >
                <component :is="icons[alert.key]" class="size-5" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm text-muted">{{ alert.title }}</span>
                <span class="tabular block text-2xl font-semibold text-ink">{{ alert.value.toLocaleString('en') }}</span>
            </span>
        </component>
    </div>
</template>
