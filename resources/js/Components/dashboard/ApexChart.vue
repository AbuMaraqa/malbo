<script setup lang="ts">
import type { ApexOptions } from 'apexcharts';
import { computed, defineAsyncComponent } from 'vue';

import { brandShade } from '@/lib/brandShade';
import { chartOptions } from '@/lib/chartOptions';
import { useTheme } from '@/lib/useTheme';

// ApexCharts only downloads on pages with a chart.
const VueApexCharts = defineAsyncComponent(() => import('vue3-apexcharts'));

const props = withDefaults(
    defineProps<{
        type: 'bar' | 'area' | 'line' | 'donut';
        series: ApexOptions['series'];
        categories?: string[];
        labels?: string[];
        height?: number;
        colors?: string[];
    }>(),
    { height: 280, colors: () => [brandShade('500', '#1570ef'), '#10b981', '#f59e0b', '#8b5cf6', '#f43f5e', brandShade('300', '#78acf7')] },
);

const { isDarkMode } = useTheme();

// Labels and names are escaped where ApexCharts writes them as HTML (lib/chartOptions).
const options = computed<ApexOptions>(() => chartOptions({ type: props.type, categories: props.categories, labels: props.labels, colors: props.colors }, isDarkMode.value));
</script>

<template>
    <VueApexCharts :type="type" :height="height" :options="options" :series="series" />
</template>
