<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { Plus, Trash2 } from '@/lib/icons';

import type { PriceForm } from './types';

/**
 * Quantity breaks: from N pieces of this product in one order (all its sizes and colors together),
 * its wholesale prices drop by a percent. The highest break reached applies, on top of the tier price.
 */
const props = defineProps<{ form: InertiaForm<PriceForm> }>();

function add(): void {
    const last = props.form.breaks[props.form.breaks.length - 1];
    props.form.breaks.push({ min_quantity: last ? Number(last.min_quantity) * 2 : 12, discount_percent: '' });
}

function remove(index: number): void {
    props.form.breaks.splice(index, 1);
}

function error(index: number, field: string): string | undefined {
    return (props.form.errors as Record<string, string | undefined>)[`breaks.${index}.${field}`];
}
</script>

<template>
    <Card :title="t('Quantity breaks')" :description="t('Counted over all sizes and colors of the product in one order. Applied on top of the tier price.')">
        <div class="grid gap-3">
            <p v-if="!form.breaks.length" class="text-sm text-muted">{{ t('No quantity breaks.') }}</p>
            <div v-for="(row, index) in form.breaks" :key="index" class="flex flex-wrap items-end gap-3">
                <TextInput v-model="row.min_quantity" class="w-40" type="number" min="2" :label="t('From quantity')" required :error="error(index, 'min_quantity')" />
                <TextInput v-model="row.discount_percent" class="w-40" type="number" min="0" max="100" step="0.01" :label="t('Discount (%)')" required :error="error(index, 'discount_percent')" />
                <Button variant="ghost" size="icon-md" :aria-label="t('Delete')" @click="remove(index)"><Trash2 /></Button>
            </div>
            <div>
                <Button size="sm" :disabled="form.breaks.length >= 20" @click="add"><Plus />{{ t('Add a break') }}</Button>
            </div>
        </div>
    </Card>
</template>
