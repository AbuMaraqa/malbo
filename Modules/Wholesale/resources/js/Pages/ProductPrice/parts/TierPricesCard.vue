<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import Card from '@/Components/ui/Card.vue';
import { controlClasses } from '@/Components/form/controlClasses';
import { t } from '@/lib/i18n';

import type { Item, PriceForm, Tier } from './types';

/**
 * Each item's own price per tier. Empty: the tier's discount off the wholesale price (the item's,
 * else the product's) applies; that computed price shows as the placeholder.
 */
const props = defineProps<{ form: InertiaForm<PriceForm>; items: Item[]; tiers: Tier[]; basePrice: number | null }>();

function suggested(item: Item, tier: Tier): string {
    const base = item.wholesale_price ?? props.basePrice;

    return base === null ? '—' : (base * (1 - tier.discount_percent / 100)).toFixed(2);
}

function error(item: Item, tier: Tier): string | undefined {
    return (props.form.errors as Record<string, string | undefined>)[`prices.${item.id}.${tier.id}`];
}
</script>

<template>
    <Card :title="t('Price per tier')" :description="t('Leave a price empty to use the tier\'s discount off the wholesale price.')">
        <p v-if="!tiers.length" class="text-sm text-muted">{{ t('Add price tiers first.') }}</p>
        <div v-else class="overflow-x-auto">
            <table class="w-full min-w-max text-sm">
                <thead>
                    <tr class="border-b border-line text-start text-muted">
                        <th class="px-2 py-2 text-start font-medium">{{ t('Item') }}</th>
                        <th class="px-2 py-2 text-start font-medium">{{ t('Wholesale Price') }}</th>
                        <th v-for="tier in tiers" :key="tier.id" class="px-2 py-2 text-start font-medium">
                            {{ tier.name }} <span class="text-xs">({{ tier.discount_percent }}%)</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="item in items" :key="item.id" class="border-b border-line last:border-0" :class="{ 'opacity-60': !item.active }">
                        <td class="px-2 py-2">
                            <p class="font-medium text-gray-800 dark:text-white/90">{{ item.name }}</p>
                            <p class="text-xs text-muted" dir="ltr">{{ item.sku }}</p>
                        </td>
                        <td class="px-2 py-2 tabular-nums">{{ item.wholesale_price ?? basePrice ?? '—' }}</td>
                        <td v-for="tier in tiers" :key="tier.id" class="px-2 py-2">
                            <input
                                v-model="form.prices[String(item.id)][String(tier.id)]"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="suggested(item, tier)"
                                :aria-label="`${item.name} — ${tier.name}`"
                                :aria-invalid="error(item, tier) ? true : undefined"
                                :class="[controlClasses, 'h-10 w-28']"
                            />
                            <p v-if="error(item, tier)" class="mt-1 text-xs text-danger">{{ error(item, tier) }}</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="form.errors.prices" class="mt-2 text-sm text-danger">{{ form.errors.prices }}</p>
    </Card>
</template>
