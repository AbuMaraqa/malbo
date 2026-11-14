<script setup lang="ts">
import AccountShell from '@/Components/storefront/AccountShell.vue';
import OrderTimeline from '@/Components/storefront/OrderTimeline.vue';
import ReturnRequestForm from '@/Components/storefront/ReturnRequestForm.vue';
import ReturnRequestList from '@/Components/storefront/ReturnRequestList.vue';
import type { AccountOrderDetail } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{ order: AccountOrderDetail }>();
</script>

<template>
    <AccountShell
        :title="`${t('Order')} ${order.number}`"
        :parents="[
            { name: t('My account'), url: route('store.account') },
            { name: t('My orders'), url: route('store.account.orders') },
        ]"
    >
        <section class="rounded-lg border border-line bg-white p-6 sm:p-8" aria-labelledby="order-title">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="order-title" class="text-xl font-bold">
                    {{ t('Order') }} <span dir="ltr">{{ order.number }}</span>
                </h2>
                <p class="rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ order.status.label }}</p>
            </div>
            <p class="mt-1 text-sm text-(--ink-soft)">
                {{ t('Placed on') }} <span dir="ltr">{{ order.placed_at }}</span>
            </p>
            <p v-if="order.status.key === 'awaiting_confirmation'" class="mt-3 text-(--ink-soft)">{{ t('We will call you soon to confirm your order.') }}</p>

            <div class="mt-8 grid gap-10 md:grid-cols-2">
                <OrderTimeline :stages="order.timeline" :final="order.final" />

                <div>
                    <h3 class="font-bold">{{ t('Items') }}</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="(line, index) in order.lines" :key="index" class="flex justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-bold">{{ line.name }}</p>
                                <p v-if="line.bundle" class="text-(--ink-soft)">{{ t('Part of :name', { name: line.bundle }) }}</p>
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
                            <dt>
                                {{ t('Discount') }}
                                <span v-if="order.coupon_code" class="text-muted">(<span dir="ltr">{{ order.coupon_code }}</span>)</span>
                            </dt>
                            <dd>−{{ order.totals.discount_label }}</dd>
                        </div>
                        <div v-else-if="order.coupon_code" class="flex justify-between">
                            <dt>{{ t('Coupon') }}</dt>
                            <dd dir="ltr">{{ order.coupon_code }}</dd>
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

        <section v-if="order.returns.length" class="rounded-lg border border-line bg-white p-6" aria-labelledby="returns-title">
            <h2 id="returns-title" class="text-xl font-bold">{{ t('Return and exchange requests') }}</h2>
            <ReturnRequestList :requests="order.returns" :order-number="order.number" class="mt-5" />
        </section>

        <section v-if="order.return_form" class="rounded-lg border border-line bg-white p-6" aria-labelledby="return-form-title">
            <h2 id="return-form-title" class="text-xl font-bold">{{ t('Return or exchange items') }}</h2>
            <ReturnRequestForm :order-number="order.number" :form="order.return_form" class="mt-5" />
        </section>
        <p v-else-if="order.return_blocked" class="rounded-lg border border-line bg-white p-5 text-(--ink-soft)">{{ order.return_blocked }}</p>
    </AccountShell>
</template>
