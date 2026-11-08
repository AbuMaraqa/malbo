<script setup lang="ts">
/**
 * Pick attribute values: the rows are their cartesian product, built here in the browser so the list
 * answers at once. The server validates and saves what is sent (ProductRequest, ProductVariations).
 * Rows stay closed until opened: a product can have dozens, each with its own editor.
 */
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { ChevronDown, Trash2 } from '@/lib/icons';
import { confirm } from '@/lib/confirm';
import type { Option } from '@/Components/form/SelectInput.vue';
import { t } from '@/lib/i18n';

import type { AttributeChoice, ProductForm, VariationRow } from './types';
import { generateVariations, inheritParentData, variationKey } from './variations';
import VariationRowFields from './VariationRowFields.vue';

const props = defineProps<{ form: InertiaForm<ProductForm>; attributes: AttributeChoice[]; categories: Option[] }>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
const hasSelection = computed(() => Object.values(props.form.attributes).some((values) => values.length > 0));

/** Rows the selection dropped, so ticking the value again brings back their edits. */
const removed = new Map<string, VariationRow>();
const opened = ref(new Set<string>());

const rowKey = (row: VariationRow, index: number): string => (row.attribute_values.length ? variationKey(row.attribute_values) : `row-${row.id ?? index}`);

const rowHasError = (index: number): boolean => Object.keys(errors.value).some((key) => key.startsWith(`variations.${index}.`) && errors.value[key]);

const isOpen = (row: VariationRow, index: number): boolean => opened.value.has(rowKey(row, index)) || rowHasError(index);

function toggle(row: VariationRow, index: number): void {
    const key = rowKey(row, index);
    const next = new Set(opened.value);

    if (next.has(key)) next.delete(key);
    else next.add(key);

    opened.value = next;
}

/** Positions change with the rows, so errors keyed by position no longer point at the right one. */
function setRows(rows: VariationRow[]): void {
    const stale = Object.keys(errors.value).filter((key) => key.startsWith('variations.'));

    if (stale.length) props.form.clearErrors(...(stale as never[]));

    props.form.variations = rows;
}

function regenerate(): void {
    setRows(generateVariations(props.form, props.attributes, removed));
}

function isChosen(attributeId: number, valueId: number): boolean {
    return (props.form.attributes[attributeId] ?? []).includes(valueId);
}

function toggleValue(attributeId: number, valueId: number): void {
    const chosen = props.form.attributes[attributeId] ?? [];

    props.form.attributes = {
        ...props.form.attributes,
        [attributeId]: chosen.includes(valueId) ? chosen.filter((id) => id !== valueId) : [...chosen, valueId],
    };
    regenerate();
}

function removeRow(index: number): void {
    setRows(props.form.variations.filter((_, position) => position !== index));
}

async function inherit(): Promise<void> {
    if (await confirm({ title: t('Inherit All Parent Data to Variations'), description: t('This replaces the prices, stock, categories and texts of every variation.') })) {
        setRows(inheritParentData(props.form));
    }
}

// Values ticked before the price or the category were filled: a row still empty follows the product.
watch(
    () => props.form.base_price,
    (price) => {
        props.form.variations.filter((row) => row.base_price === '' || row.base_price === null).forEach((row) => (row.base_price = price));
    },
);

watch(
    () => props.form.category_id,
    (categories) => {
        props.form.variations.filter((row) => !row.category_id.length).forEach((row) => (row.category_id = [...categories]));
    },
);

// The rows' names carry the product's name.
watch(
    () => props.form.name,
    () => {
        if (hasSelection.value) regenerate();
    },
);
</script>

<template>
    <div class="grid gap-5">
        <fieldset v-for="attribute in attributes" :key="attribute.id" class="rounded-lg border border-line p-3">
            <legend class="px-1 text-sm font-medium">{{ attribute.name }}</legend>
            <div class="flex flex-wrap gap-x-4 gap-y-2">
                <label v-for="value in attribute.values" :key="value.id" class="inline-flex items-center gap-1.5 text-sm">
                    <input type="checkbox" class="size-4 rounded accent-brand-500" :checked="isChosen(attribute.id, value.id)" @change="toggleValue(attribute.id, value.id)" />
                    <span v-if="value.color" class="inline-block size-3 rounded-full border border-line" :style="{ backgroundColor: value.color }" />
                    {{ value.name }}
                </label>
            </div>
        </fieldset>
        <p v-if="!attributes.length" class="text-sm text-muted">{{ t('No attributes yet') }}</p>

        <div v-if="hasSelection" class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium">{{ t('Product Variations') }} ({{ form.variations.length }})</p>
            <Button size="sm" @click="inherit">{{ t('Inherit All Parent Data to Variations') }}</Button>
        </div>

        <div v-if="form.variations.length" class="space-y-2">
            <div v-for="(row, index) in form.variations" :key="rowKey(row, index)" class="rounded-lg border bg-card" :class="rowHasError(index) ? 'border-red-500' : 'border-line'">
                <div class="flex items-center gap-1 px-2 py-1.5">
                    <button type="button" class="flex min-w-0 flex-1 items-center gap-1 rounded p-1 text-start text-sm font-medium hover:bg-surface" :aria-expanded="isOpen(row, index)" @click="toggle(row, index)">
                        <ChevronDown class="size-4 shrink-0 text-muted transition-transform" :class="{ 'rotate-180': isOpen(row, index) }" />
                        <span class="truncate">{{ row.name || t('Untitled') }}</span>
                        <span v-if="rowHasError(index)" class="ms-1 inline-block size-1.5 shrink-0 rounded-full bg-danger" />
                    </button>
                    <span class="px-2 text-sm text-muted tabular-nums">{{ row.base_price }}</span>
                    <Button variant="ghost" size="icon" :aria-label="t('Remove')" @click="removeRow(index)"><Trash2 class="size-4" /></Button>
                </div>
                <div v-if="isOpen(row, index)" class="border-t border-line p-3">
                    <VariationRowFields :row="row" :index="index" :errors="errors" :categories="categories" />
                </div>
            </div>
        </div>

        <!-- The whole list, for an error on the list itself. -->
        <p v-if="errors.variations" class="text-sm text-danger" role="alert">{{ errors.variations }}</p>
    </div>
</template>
