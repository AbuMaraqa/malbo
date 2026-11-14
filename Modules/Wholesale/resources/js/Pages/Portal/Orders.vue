<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { btnBrand, card, pageLink } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import type { PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

interface OrderRow {
    number: string;
    placed_at: string | null;
    status: { key: string; label: string };
    pieces: number;
    total_label: string;
    payment: string | null;
    url: string;
}

defineProps<{
    nav: PortalLink[];
    orders: { data: OrderRow[]; current_page: number; last_page: number; prev_url: string | null; next_url: string | null; total: number };
}>();
</script>

<template>
    <PortalShell :title="t('My wholesale orders')" :nav="nav" current="orders">
        <div v-if="!orders.data.length" class="rounded-lg border border-dashed border-line p-12 text-center">
            <p>{{ t('No wholesale orders yet.') }}</p>
            <Link :href="route('store.wholesale.catalog')" class="mt-6" :class="btnBrand">{{ t('Wholesale catalog') }}</Link>
        </div>

        <ul v-else class="grid gap-3">
            <li v-for="order in orders.data" :key="order.number">
                <Link :href="order.url" :class="card" class="flex flex-wrap items-center justify-between gap-3 p-5 hover:border-store">
                    <span>
                        <span class="block font-bold" dir="ltr">{{ order.number }}</span>
                        <span class="block text-sm text-muted">
                            <span dir="ltr">{{ order.placed_at }}</span> · {{ t(':count pieces', { count: order.pieces }) }}<template v-if="order.payment"> · {{ order.payment }}</template>
                        </span>
                    </span>
                    <span class="flex items-center gap-4">
                        <span class="rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ order.status.label }}</span>
                        <span class="font-bold">{{ order.total_label }}</span>
                    </span>
                </Link>
            </li>
        </ul>

        <nav v-if="orders.last_page > 1" :aria-label="t('Pagination')" class="mt-8 flex justify-center gap-2">
            <Link v-if="orders.prev_url" :href="orders.prev_url" rel="prev" :class="pageLink">{{ t('Previous') }}</Link>
            <span class="inline-grid h-10 place-items-center px-3 text-sm text-muted">{{ orders.current_page }} / {{ orders.last_page }}</span>
            <Link v-if="orders.next_url" :href="orders.next_url" rel="next" :class="pageLink">{{ t('Next') }}</Link>
        </nav>
    </PortalShell>
</template>
