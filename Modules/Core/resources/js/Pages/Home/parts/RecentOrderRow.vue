<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import Badge from '@/Components/ui/Badge.vue';

import type { RecentOrder } from './types';

defineProps<{ order: RecentOrder }>();
</script>

<template>
    <tr>
        <td class="py-3 pe-4 text-sm font-medium text-gray-800 dark:text-white/90">
            <Link v-if="order.url" :href="order.url" class="text-brand-600 dark:text-brand-400 hover:underline dark:text-brand-400" dir="ltr">{{ order.order_number }}</Link>
            <span v-else dir="ltr">{{ order.order_number }}</span>
        </td>
        <td class="py-3 pe-4 text-sm text-gray-500 dark:text-gray-400">{{ order.customer ?? '-' }}</td>
        <td class="py-3 pe-4 text-sm text-gray-500 tabular-nums dark:text-gray-400">{{ order.total }}</td>
        <td class="py-3 pe-4 text-sm">
            <Badge v-if="order.status" :tone="order.tone">{{ order.status }}</Badge>
        </td>
        <td class="py-3 text-sm whitespace-nowrap text-gray-500 tabular-nums dark:text-gray-400" dir="ltr">{{ order.date }}</td>
    </tr>
</template>
