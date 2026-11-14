<script setup lang="ts">
/**
 * The sets and bundle offers on an order, each with its pieces as they were sold. A set is kept as
 * it is or removed whole: its pieces never change one by one (the server refuses it too).
 */
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { Trash2 } from '@/lib/icons';

import type { OrderItemRow } from './types';

const props = defineProps<{ items: OrderItemRow[]; locked?: boolean }>();

const emit = defineEmits<{ remove: [key: string] }>();

const groups = computed(() => {
    const byKey = new Map<string, { key: string; name: string; quantity: number; unitPrice: number; pieces: OrderItemRow[] }>();

    for (const item of props.items) {
        if (!item.bundle) continue;

        const group = byKey.get(item.bundle.key) ?? { key: item.bundle.key, name: item.bundle.name ?? '', quantity: item.bundle.quantity, unitPrice: item.bundle.unit_price, pieces: [] };
        group.pieces.push(item);
        byKey.set(item.bundle.key, group);
    }

    return [...byKey.values()];
});
</script>

<template>
    <div class="space-y-3">
        <div v-for="group in groups" :key="group.key" class="rounded-lg border border-line bg-surface/40 p-3">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="font-medium">{{ group.name }} <span class="text-muted">×{{ group.quantity }}</span></p>
                    <p class="text-sm text-muted">{{ t('Set price') }}: {{ group.unitPrice.toFixed(2) }}</p>
                </div>
                <Button v-if="!locked" variant="ghost" size="sm" @click="emit('remove', group.key)"><Trash2 class="size-4" />{{ t('Remove set') }}</Button>
            </div>
            <ul class="mt-2 divide-y divide-line text-sm">
                <li v-for="piece in group.pieces" :key="piece.id" class="flex justify-between gap-3 py-1.5">
                    <span>{{ piece.name }}</span>
                    <span class="text-muted tabular-nums">×{{ piece.quantity }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
