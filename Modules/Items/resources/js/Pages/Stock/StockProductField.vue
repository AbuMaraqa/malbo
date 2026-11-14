<script setup lang="ts">
import { ref } from 'vue';

import RemoteSelect from '@/Components/form/RemoteSelect.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** A product that holds stock (a variant, or a product without variants), found by name or SKU. */
export interface StockProduct {
    value: number;
    label: string;
    stock: number;
    /** Pieces on their way. */
    incoming?: number;
}

const props = defineProps<{ selected: StockProduct | null; error?: string; /** On the adjustment page: goods that arrive are received instead. */ incomingHint?: boolean }>();

const model = defineModel<number | string | null>();
const picked = ref<StockProduct | null>(props.selected);
</script>

<template>
    <div>
        <RemoteSelect
            v-model="model"
            :url="route('stock.products')"
            :initial="selected ? [selected] : []"
            :label="t('Product')"
            :placeholder="t('Name or SKU')"
            required
            :error="error"
            @picked="(option: StockProduct) => (picked = option)"
        />
        <p v-if="picked && String(picked.value) === String(model)" class="mt-1.5 text-sm text-muted">
            {{ t('Stock now') }}: <span class="tabular font-medium text-ink">{{ picked.stock }}</span>
        </p>
        <p v-if="incomingHint && picked && String(picked.value) === String(model) && (picked.incoming ?? 0) > 0" class="mt-1 text-sm text-warning-700 dark:text-warning-400">
            {{ t('Goods on their way (:count)? Use Receive on the stock page so the incoming goods go down.', { count: picked.incoming ?? 0 }) }}
        </p>
    </div>
</template>
