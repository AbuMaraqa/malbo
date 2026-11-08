<script setup lang="ts">
/** The sub-form of one variation: its text, then its own tabs. Mounted only while the row is open. */
import { computed, ref, watch } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import MultiSelect from '@/Components/form/MultiSelect.vue';
import RichEditor from '@/Components/form/RichEditor.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import { t } from '@/lib/i18n';

import { rowTabsWithError } from './fieldTabs';
import GeneralTab from './GeneralTab.vue';
import InventoryTab from './InventoryTab.vue';
import MediaTab from './MediaTab.vue';
import PriceTab from './PriceTab.vue';
import SeoTab from './SeoTab.vue';
import type { VariationRow } from './types';

const props = defineProps<{ row: VariationRow; index: number; errors: Record<string, string | undefined>; categories: Option[] }>();

const prefix = computed(() => `variations.${props.index}.`);
const error = (field: string): string | undefined => props.errors[`${prefix.value}${field}`];
const withErrors = computed(() => rowTabsWithError(props.errors, prefix.value));

const tabs = computed(() =>
    [
        { value: 'general', label: t('General') },
        { value: 'price', label: t('Price') },
        { value: 'inventory', label: t('Inventory') },
        { value: 'images', label: t('Images') },
        { value: 'categories', label: t('Categories') },
        { value: 'seo', label: t('Meta Information') },
    ].map((tab) => ({ ...tab, hasError: withErrors.value.has(tab.value) })),
);

const active = ref('general');

// A row opened by an error shows the tab that holds it.
watch(
    withErrors,
    (names) => {
        const first = tabs.value.find((tab) => names.has(tab.value));
        if (first && !names.has(active.value)) active.value = first.value;
    },
    { immediate: true },
);
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-4 md:grid-cols-2">
            <TextInput :model-value="row.name" :label="t('Name')" disabled :error="error('name')" />
            <TextInput v-model="row.slug" :label="t('Slug')" dir="ltr" :error="error('slug')" :hint="t('Leave empty to make it from the name')" />
            <TextareaInput v-model="row.short_description" class="md:col-span-2" :label="t('Short Description')" :error="error('short_description')" />
            <RichEditor v-model="row.description" class="md:col-span-2" :label="t('Description')" :error="error('description')" />
        </div>

        <Tabs v-model="active" :tabs="tabs">
            <template #general><GeneralTab :row="row" :error="error" /></template>
            <template #price><PriceTab :row="row" :error="error" /></template>
            <template #inventory><InventoryTab :row="row" :error="error" /></template>
            <template #images><MediaTab :row="row" :error="error" /></template>
            <template #categories>
                <MultiSelect v-model="row.category_id" :label="t('Category')" required :options="categories" :error="error('category_id')" />
            </template>
            <template #seo><SeoTab :row="row" :error="error" /></template>
        </Tabs>
    </div>
</template>
