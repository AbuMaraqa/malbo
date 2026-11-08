<script setup lang="ts">
import { computed } from 'vue';

import ApexChart from '@/Components/dashboard/ApexChart.vue';
import { t } from '@/lib/i18n';

import type { MonthlySeries } from './types';
import WidgetCard from './WidgetCard.vue';
import WidgetEmpty from './WidgetEmpty.vue';

const props = defineProps<{ chart: MonthlySeries }>();

const isEmpty = computed(() => props.chart.data.every((value) => value === 0));
const series = computed(() => [{ name: t('New users'), data: props.chart.data }]);
</script>

<template>
    <WidgetCard :title="t('New users per month')" :description="t('The last 12 months')">
        <WidgetEmpty v-if="isEmpty" :message="t('No new users in the last 12 months')" />
        <div v-else class="custom-scrollbar max-w-full overflow-x-auto">
            <div class="min-w-250 xl:min-w-full">
                <ApexChart type="area" :height="310" :series="series" :categories="chart.categories" />
            </div>
        </div>
    </WidgetCard>
</template>
