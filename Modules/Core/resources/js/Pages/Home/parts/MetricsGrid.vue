<script setup lang="ts">
import { Package, ShoppingCart, UserRound, UsersRound } from '@/lib/icons';

import MetricCard from '@/tailadmin/dashboard/MetricCard.vue';

import type { Metric } from './types';

defineProps<{ metrics: Metric[] }>();

const icons = { users: UsersRound, clients: UserRound, products: Package, orders: ShoppingCart };
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:gap-6">
        <MetricCard v-for="metric in metrics" :key="metric.key" :title="metric.title" :value="metric.value.toLocaleString('en')">
            <template #icon><component :is="icons[metric.key]" /></template>
        </MetricCard>
    </div>
</template>
