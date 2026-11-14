<script setup lang="ts">
import StatCard from '@/Components/dashboard/StatCard.vue';
import { Package, ShoppingCart, UserRound, UsersRound } from '@/lib/icons';

import type { Metric } from './types';

defineProps<{ metrics: Metric[] }>();

const icons = { users: UsersRound, clients: UserRound, products: Package, orders: ShoppingCart };
const tones = { users: 'brand', clients: 'info', products: 'warning', orders: 'success' } as const;
</script>

<template>
    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
        <StatCard v-for="metric in metrics" :key="metric.key" :label="metric.title" :value="metric.value.toLocaleString('en')" :tone="tones[metric.key]">
            <template #icon><component :is="icons[metric.key]" /></template>
        </StatCard>
    </div>
</template>
