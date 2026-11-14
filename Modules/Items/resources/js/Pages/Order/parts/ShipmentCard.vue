<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Card from '@/Components/ui/Card.vue';
import { can, t } from '@/lib/i18n';

import type { ShipmentCardData } from './shipment';
import ShipmentCreateForm from './ShipmentCreateForm.vue';
import ShipmentEvents from './ShipmentEvents.vue';
import ShipmentMoveForm from './ShipmentMoveForm.vue';

/** The order's shipment: who carries it, its tracking number and status, and how to move it. */
defineProps<{ orderId: number; shipment: ShipmentCardData }>();
</script>

<template>
    <Card :title="t('Shipment')">
        <div v-if="shipment.current" class="grid gap-5">
            <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-muted">{{ t('Courier') }}</dt>
                    <dd class="font-medium text-ink">{{ shipment.current.courier }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Tracking number') }}</dt>
                    <dd class="font-medium text-ink" dir="ltr">{{ shipment.current.tracking_number ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Status') }}</dt>
                    <dd><Badge :tone="shipment.current.status.tone">{{ shipment.current.status.label }}</Badge></dd>
                </div>
                <div>
                    <dt class="text-muted">{{ t('Shipping cost') }}</dt>
                    <dd class="tabular font-medium text-ink">{{ shipment.current.cost }}</dd>
                </div>
                <div v-if="shipment.current.shipped_at">
                    <dt class="text-muted">{{ t('Shipped at') }}</dt>
                    <dd dir="ltr" class="text-start">{{ shipment.current.shipped_at }}</dd>
                </div>
                <div v-if="shipment.current.delivered_at">
                    <dt class="text-muted">{{ t('Delivered at') }}</dt>
                    <dd dir="ltr" class="text-start">{{ shipment.current.delivered_at }}</dd>
                </div>
                <div v-if="shipment.current.notes" class="sm:col-span-2">
                    <dt class="text-muted">{{ t('Notes') }}</dt>
                    <dd class="whitespace-pre-line">{{ shipment.current.notes }}</dd>
                </div>
            </dl>

            <ShipmentEvents :events="shipment.current.events" />

            <ShipmentMoveForm
                v-if="can('Shipment Update') && shipment.current.transitions.length"
                :key="shipment.current.status.value"
                :shipment-id="shipment.current.id"
                :transitions="shipment.current.transitions"
            />
        </div>

        <ShipmentCreateForm v-else-if="shipment.can_create && can('Shipment Create')" :order-id="orderId" :couriers="shipment.couriers" />

        <p v-else class="text-sm text-muted">{{ t('No shipment yet. A shipment can be made for a delivery order once it is being prepared (or ready), until it reaches the buyer.') }}</p>
    </Card>
</template>
