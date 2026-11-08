<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import { t } from '@/lib/i18n';

import type { LabelledSeries } from './types';
import WidgetCard from './WidgetCard.vue';
import WidgetEmpty from './WidgetEmpty.vue';

const props = defineProps<{ chart: LabelledSeries }>();

const isEmpty = computed(() => props.chart.data.length === 0);
</script>

<template>
    <WidgetCard :title="t('Orders by status')">
        <WidgetEmpty v-if="isEmpty" :message="t('No orders yet')" />
        <ApexChart v-else type="donut" :height="320" :series="chart.data" :labels="chart.labels" />
    </WidgetCard>
</template>
