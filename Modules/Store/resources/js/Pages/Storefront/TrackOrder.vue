<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import OrderTimeline from '@/Components/storefront/OrderTimeline.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import StoreField from '@/Components/storefront/StoreField.vue';
import { btnBrand } from '@/Components/storefront/styles';
import type { TrackingResult } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ number: string; result: TrackingResult | null }>();

const form = useForm({ number: props.number, phone: '' });

function find(): void {
    form.post(route('store.track.find'), { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <PageHero :title="t('Track your order')" :lead="t('Enter your order number and the phone number you ordered with.')" />
    <div class="mx-auto max-w-3xl px-4 pt-12">

        <form class="grid gap-5 rounded-lg border border-line bg-(--shell) p-6 sm:grid-cols-[1fr_1fr_auto] sm:items-end" novalidate @submit.prevent="find">
            <StoreField v-model="form.number" :label="t('Order number')" dir="ltr" inputmode="text" required :maxlength="40" :error="form.errors.number" />
            <StoreField v-model="form.phone" :label="t('Phone')" type="tel" autocomplete="tel" inputmode="tel" dir="ltr" required :maxlength="40" :error="form.errors.phone" />
            <button type="submit" :class="btnBrand" :disabled="form.processing">{{ t('Track') }}</button>
        </form>

        <section v-if="result" class="mt-8 rounded-lg border border-line bg-(--shell) p-6 sm:p-8" aria-labelledby="result-title" aria-live="polite">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 id="result-title" class="text-xl font-bold">
                    {{ t('Order') }} <span dir="ltr">{{ result.number }}</span>
                </h2>
                <p class="rounded-full bg-(--sand-light) px-3 py-1 text-sm font-bold">{{ result.status.label }}</p>
            </div>
            <p v-if="result.status.key === 'awaiting_confirmation'" class="mt-3 text-(--ink-soft)">{{ t('We will call you soon to confirm your order.') }}</p>
            <dl v-if="result.shipment" class="mt-4 flex flex-wrap gap-x-8 gap-y-2 rounded-md bg-(--sand-light) px-4 py-3 text-sm">
                <div class="flex gap-2">
                    <dt class="text-(--ink-soft)">{{ t('Courier') }}</dt>
                    <dd class="font-bold">{{ result.shipment.courier }}</dd>
                </div>
                <div v-if="result.shipment.tracking_number" class="flex gap-2">
                    <dt class="text-(--ink-soft)">{{ t('Tracking number') }}</dt>
                    <dd class="font-bold" dir="ltr">{{ result.shipment.tracking_number }}</dd>
                </div>
            </dl>

            <div class="mt-8 grid gap-10 md:grid-cols-2">
                <OrderTimeline :stages="result.timeline" :final="result.final" />

                <div>
                    <h3 class="font-bold">{{ t('Items') }}</h3>
                    <ul class="mt-3 divide-y divide-line">
                        <li v-for="(line, index) in result.lines" :key="index" class="flex justify-between gap-3 py-3 text-sm">
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
                            <dd>{{ result.totals.subtotal_label }}</dd>
                        </div>
                        <div v-if="result.totals.discount_label" class="flex justify-between">
                            <dt>{{ t('Discount') }}</dt>
                            <dd>−{{ result.totals.discount_label }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt>{{ t('Delivery') }}</dt>
                            <dd>{{ result.totals.delivery_fee_label }}</dd>
                        </div>
                        <div class="flex justify-between text-base font-bold">
                            <dt>{{ t('Total') }}</dt>
                            <dd>{{ result.totals.total_label }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>
    </div>
</template>
