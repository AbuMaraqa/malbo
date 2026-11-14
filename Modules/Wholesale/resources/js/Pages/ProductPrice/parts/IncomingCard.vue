<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { Item } from './types';

/**
 * Incoming goods per item: how many pieces are on their way and when they are expected. Merchants may
 * pre-order them; the storefront never sells them. Saved on its own, apart from the prices. Only the
 * rows changed here are sent, each with what the page showed: goods received meanwhile (the stock
 * pages lower the incoming pieces) are never put back by a save, and a changed row is refused.
 */
const props = defineProps<{ productId: number; items: Item[] }>();

type Row = { quantity: number | string; expected_at: string | null };

const rows = (): Record<string, Row> => Object.fromEntries(props.items.map((item) => [String(item.id), { quantity: item.incoming_quantity, expected_at: item.incoming_expected_at }]));

const form = useForm<{ incoming: Record<string, Row> }>({ incoming: rows() });

// After a save the page sends the items again: what they show is what the next save checks.
watch(
    () => props.items,
    () => {
        form.defaults({ incoming: rows() });
        form.reset();
    },
);

const loaded = (id: string) => props.items.find((item) => String(item.id) === id);

const changed = computed(() =>
    Object.entries(form.incoming).filter(([id, row]) => {
        const item = loaded(id);

        return item !== undefined && (Number(row.quantity) !== item.incoming_quantity || (row.expected_at || null) !== item.incoming_expected_at);
    }),
);

function error(id: number, field: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[`incoming.${id}.${field}`];
}

function submit(): void {
    if (!changed.value.length) return;

    form.transform(() => ({
        incoming: Object.fromEntries(
            changed.value.map(([id, row]) => [
                id,
                { ...row, loaded_quantity: loaded(id)?.incoming_quantity ?? 0, loaded_expected_at: loaded(id)?.incoming_expected_at ?? null },
            ]),
        ),
    })).put(route('wholesale-prices.incoming', props.productId), { preserveScroll: true });
}
</script>

<template>
    <Card :title="t('Incoming goods')" :description="t('Merchants may pre-order incoming goods that have an expected date. The storefront never sells them.')">
        <form class="grid gap-3" @submit.prevent="submit">
            <p v-if="form.errors.incoming" class="text-sm text-error-600 dark:text-error-400">{{ form.errors.incoming }}</p>
            <div v-for="item in items" :key="item.id" class="grid items-end gap-3 border-b border-line pb-3 last:border-0 sm:grid-cols-[minmax(0,1fr)_10rem_12rem]">
                <div class="min-w-0">
                    <p class="truncate font-medium text-ink">{{ item.name }}</p>
                    <p class="text-xs text-muted">
                        <span dir="ltr">{{ item.sku }}</span>
                        <template v-if="item.manage_stock">
                            · {{ t('Stock') }}: <span dir="ltr" :class="item.stock_quantity < 0 ? 'text-error-600 dark:text-error-400' : ''">{{ item.stock_quantity }}</span>
                        </template>
                    </p>
                </div>
                <TextInput v-model="form.incoming[String(item.id)].quantity" type="number" min="0" :label="t('Incoming quantity')" :error="error(item.id, 'quantity')" />
                <TextInput v-model="form.incoming[String(item.id)].expected_at" type="date" :label="t('Expected on')" :error="error(item.id, 'expected_at')" />
            </div>
            <div>
                <Button type="submit" variant="primary" :disabled="form.processing || !changed.length">{{ t('Save incoming goods') }}</Button>
            </div>
        </form>
    </Card>
</template>
