<script setup lang="ts">
import { t } from '@/lib/i18n';

import type { FacetAttribute } from './types';

/**
 * One attribute's filter: its values as checkboxes, colors as swatches, each with how many products
 * it would show. Several values of one attribute widen the results (OR).
 */
defineProps<{ attribute: FacetAttribute }>();

const emit = defineEmits<{ toggle: [valueId: number] }>();
</script>

<template>
    <fieldset>
        <legend class="mb-3 text-[0.9375rem] font-bold">{{ attribute.name }}</legend>

        <div v-if="attribute.swatch" class="flex flex-wrap gap-2">
            <label v-for="value in attribute.values" :key="value.id" class="cursor-pointer" :title="`${value.name} (${value.count})`">
                <input type="checkbox" class="peer sr-only" :checked="value.selected" @change="emit('toggle', value.id)" />
                <span
                    class="block size-10 rounded-full border border-line p-1 transition-colors peer-checked:border-2 peer-checked:border-store peer-focus-visible:outline-2 peer-focus-visible:outline-store"
                >
                    <span class="block size-full rounded-full border border-black/10" :style="{ backgroundColor: value.color ?? 'transparent' }"></span>
                </span>
                <span class="sr-only">{{ value.name }}, {{ t(':count products', { count: value.count }) }}</span>
            </label>
        </div>

        <ul v-else class="space-y-1">
            <li v-for="value in attribute.values" :key="value.id">
                <label class="flex min-h-10 cursor-pointer items-center gap-3 rounded-md px-1 hover:bg-(--sand-light)">
                    <input type="checkbox" class="size-4.5 accent-store" :checked="value.selected" @change="emit('toggle', value.id)" />
                    <span class="flex-1" :class="{ 'font-semibold text-store-600': value.selected }">{{ value.name }}</span>
                    <span class="text-sm text-muted" aria-hidden="true">{{ value.count }}</span>
                    <span class="sr-only">{{ t(':count products', { count: value.count }) }}</span>
                </label>
            </li>
        </ul>
    </fieldset>
</template>
