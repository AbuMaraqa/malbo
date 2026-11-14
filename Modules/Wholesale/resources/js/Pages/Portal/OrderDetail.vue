<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import OrderTimeline from '@/Components/storefront/OrderTimeline.vue';
import { btnOutline, card } from '@/Components/storefront/styles';
import type { TrackingResult } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import type { PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    nav: PortalLink[];
    order: TrackingResult & { payment: string | null; delivery_address: string | null; notes: string | null; reorder_url: string };
}>();
</script>

<template>
    <PortalShell :title="`${t('Order')} ${order.number}`" :nav="nav" current="orders" :parents="[{ name: t('My wholesale orders'), url: route('store.wholesale.orders') }]">
        <section :class="card" class="p-6 sm:p-8" aria-labelledby="order-title">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="order-title" class="text-xl font-bold">
                    {{ t('Order') }} <span dir="ltr">{{ order.number }}</span>
                </h2>
                <div class="flex flex-wrap items-center gap-3">
                    <p class="rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ order.status.label }}</p>
                    <Link :href="order.reorder_url" method="post" as="button" :class="btnOutline">{{ t('Reorder') }}</Link>
                </div>
            </div>
            <p class="mt-1 text-sm text-(--ink-soft)">
                {{ t('Placed on') }} <span dir="ltr">{{ order.placed_at }}</span>
                <template v-if="order.payment"> · {{ order.payment }}</template>
            </p>
            <p v-if="order.status.key === 'awaiting_confirmation'" class="mt-3 text-(--ink-soft)">{{ t('MALBO will contact you to confirm the order and arrange its delivery.') }}</p>
            <p v-if="order.delivery_address" class="mt-3 text-sm">{{ t('Delivery address') }}: {{ order.delivery_address }}</p>
            <p v-if="order.notes" class="mt-1 text-sm text-(--ink-soft)">{{ t('Notes') }}: {{ order.notes }}</p>

            <div class="mt-8 grid gap-10 md:grid-cols-2">
                <OrderTimeline :stages="order.timeline" :final="order.final" />

                <div>
                    <h3 class="font-bold">{{ t('Items') }}</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="(line, index) in order.lines" :key="index" class="flex justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-bold">{{ line.name }}</p>
                                <p v-if="line.options.length" class="text-(--ink-soft)">{{ line.options.map((option) => `${option.name}: ${option.value}`).join(' · ') }}</p>
                                <p class="text-muted">{{ t('Quantity') }}: {{ line.quantity }}</p>
                            </div>
                            <p class="font-bold">{{ line.total_label }}</p>
                        </li>
                    </ul>
                    <dl class="mt-3 space-y-1.5 border-t border-line pt-3 text-sm">
                        <div class="flex justify-between">
                            <dt>{{ t('Subtotal') }}</dt>
                            <dd>{{ order.totals.subtotal_label }}</dd>
                        </div>
                        <div v-if="order.totals.discount_label" class="flex justify-between">
                            <dt>{{ t('Discount') }}</dt>
                            <dd>−{{ order.totals.discount_label }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>{{ t('Delivery') }}</dt>
                            <dd>{{ order.totals.delivery_fee_label }}</dd>
                        </div>
                        <div class="flex justify-between text-base font-bold">
                            <dt>{{ t('Total') }}</dt>
                            <dd>{{ order.totals.total_label }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </PortalShell>
</template>
