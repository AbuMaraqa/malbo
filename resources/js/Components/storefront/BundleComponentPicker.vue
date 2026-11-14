<script setup lang="ts">
import { computed, reactive, watch } from 'vue';

import { t } from '@/lib/i18n';

import type { BundleComponentOption } from './types';
import VariantPicker from './VariantPicker.vue';

/**
 * One piece of a set or bundle: its picture and name, and the size (or colour) the buyer picks for
 * it. The model is the variant those values make, or undefined until every value is picked. A piece
 * without a choice shows what it is.
 */
const props = defineProps<{ component: BundleComponentOption }>();

const model = defineModel<number | undefined>();

/** attribute id => value id, started from the variant already chosen. */
const selected = reactive<Record<string, number | undefined>>(
    Object.fromEntries(Object.entries(props.component.variants.find((variant) => variant.id === model.value)?.values ?? {}).map(([attribute, value]) => [attribute, value])),
);

const variant = computed(() => {
    if (props.component.attributes.some((attribute) => selected[attribute.id] === undefined)) return null;

    return props.component.variants.find((candidate) => props.component.attributes.every((attribute) => candidate.values[attribute.id] === selected[attribute.id])) ?? null;
});

const complete = computed(() => props.component.attributes.every((attribute) => selected[attribute.id] !== undefined));
const image = computed(() => variant.value?.image ?? props.component.image);

watch(variant, (value) => {
    model.value = value?.id;
});
</script>

<template>
    <li class="flex gap-4 py-5">
        <div class="size-20 shrink-0 overflow-hidden rounded-md bg-(--sand)">
            <img v-if="image" :src="image" :alt="component.name" class="size-full object-cover" loading="lazy" />
        </div>
        <div class="min-w-0 flex-1 space-y-3">
            <p class="font-bold">
                {{ component.name }}
                <span v-if="component.quantity > 1" class="font-normal text-muted">×{{ component.quantity }}</span>
            </p>
            <p v-if="component.fixed" class="text-sm text-(--ink-soft)">{{ component.fixed }}</p>
            <template v-if="component.choosable">
                <VariantPicker
                    v-for="attribute in component.attributes"
                    :key="attribute.id"
                    v-model="selected[attribute.id]"
                    :attribute="attribute"
                    :name="`component-${component.id}-attribute-${attribute.id}`"
                />

                <p v-if="complete && !variant" role="alert" class="text-sm font-semibold text-(--danger)">{{ t('This combination is not available') }}</p>
                <p v-else-if="variant && !variant.in_stock" role="alert" class="text-sm font-semibold text-(--danger)">{{ t('Currently unavailable') }}</p>
            </template>
            <p v-else-if="!component.available" class="text-sm font-semibold text-(--danger)">{{ t('Currently unavailable') }}</p>
        </div>
    </li>
</template>
