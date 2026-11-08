<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';

import RecentOrderRow from './RecentOrderRow.vue';
import type { RecentOrders } from './types';
import WidgetCard from './WidgetCard.vue';
import WidgetEmpty from './WidgetEmpty.vue';

defineProps<{ orders: RecentOrders }>();
</script>

<template>
    <WidgetCard :title="t('Recent Orders')">
        <template v-if="orders.url" #actions>
            <Button size="sm" :href="orders.url">{{ t('See all') }}</Button>
        </template>

        <WidgetEmpty v-if="orders.rows.length === 0" :message="t('No orders yet')" />
        <div v-else class="max-w-full overflow-x-auto">
            <table class="min-w-full">
                <thead class="border-y border-gray-100 dark:border-gray-800">
                    <tr>
                        <th class="py-3 pe-4 text-start text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Order Number') }}</th>
                        <th class="py-3 pe-4 text-start text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Customer') }}</th>
                        <th class="py-3 pe-4 text-start text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Total') }}</th>
                        <th class="py-3 pe-4 text-start text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Status') }}</th>
                        <th class="py-3 text-start text-xs font-medium text-gray-500 dark:text-gray-400">{{ t('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    <RecentOrderRow v-for="order in orders.rows" :key="order.id" :order="order" />
                </tbody>
            </table>
        </div>
    </WidgetCard>
</template>
