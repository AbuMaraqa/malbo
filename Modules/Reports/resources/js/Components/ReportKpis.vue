<script setup lang="ts">
import type { Component } from 'vue';

import StatCard from '@/Components/dashboard/StatCard.vue';
import { DollarSign, GraphUp, Package, Returns, ShoppingCart, Tag, Truck, Users } from '@/lib/icons';

import { formatChange } from './reportQuery';
import type { ReportKpi } from './types';

/** A report's headline figures, each with its change against the period before when it has one. */
defineProps<{ kpis: ReportKpi[] }>();

const icons: Record<string, Component> = {
    orders: ShoppingCart,
    units: Package,
    gross: DollarSign,
    net: DollarSign,
    total: DollarSign,
    aov: DollarSign,
    discounts: Tag,
    returns: Returns,
    delivery: Truck,
    merchants: Users,
};

const tones: Record<string, 'brand' | 'success' | 'warning' | 'info'> = {
    net: 'success',
    total: 'success',
    returns: 'warning',
    discounts: 'warning',
    orders: 'info',
    units: 'info',
};

const change = (value: number | null | undefined): string | undefined => formatChange(value) ?? undefined;
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            v-for="kpi in kpis"
            :key="kpi.key"
            :label="kpi.hint ? `${kpi.label} · ${kpi.hint}` : kpi.label"
            :value="typeof kpi.value === 'number' ? kpi.value.toLocaleString('en') : kpi.value"
            :change="change(kpi.change)"
            :direction="(kpi.change ?? 0) < 0 ? 'down' : 'up'"
            :tone="tones[kpi.key] ?? 'brand'"
        >
            <template #icon><component :is="icons[kpi.key] ?? GraphUp" /></template>
        </StatCard>
    </div>
</template>
