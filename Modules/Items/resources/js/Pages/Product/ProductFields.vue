<script setup lang="ts">
/**
 * The product form Add and Edit share. A validation error marks its tab and opens it, then the first
 * visible message scrolls into view: on a form this long it may be nowhere near the save button.
 */
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';

import Card from '@/Components/ui/Card.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import { t } from '@/lib/i18n';

import AddonsTab from './parts/AddonsTab.vue';
import { tabsWithError } from './parts/fieldTabs';
import GeneralInfoCard from './parts/GeneralInfoCard.vue';
import GeneralTab from './parts/GeneralTab.vue';
import InventoryTab from './parts/InventoryTab.vue';
import MediaTab from './parts/MediaTab.vue';
import PriceTab from './parts/PriceTab.vue';
import SeoTab from './parts/SeoTab.vue';
import type { ProductForm, ProductFormOptions } from './parts/types';
import VariationsTab from './parts/VariationsTab.vue';

const props = defineProps<{ form: InertiaForm<ProductForm>; options: ProductFormOptions; imageRequired?: boolean; collapsed?: boolean }>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
const error = (field: string): string | undefined => errors.value[field];
const withErrors = computed(() => tabsWithError(Object.keys(errors.value)));

const tabs = computed(() =>
    [
        { value: 'general', label: t('General') },
        { value: 'price', label: t('Price') },
        { value: 'inventory', label: t('Inventory') },
        { value: 'addons', label: t('Addons') },
        { value: 'variations', label: t('Generate Variations') },
        { value: 'images', label: t('Images') },
        { value: 'seo', label: t('Meta Information') },
    ].map((tab) => ({ ...tab, hasError: withErrors.value.has(tab.value) })),
);

const active = ref('general');
const root = ref<HTMLElement>();

watch(errors, async () => {
    const first = tabs.value.find((tab) => tab.hasError);

    if (first && !withErrors.value.has(active.value)) active.value = first.value;

    await nextTick();

    const message = [...(root.value?.querySelectorAll<HTMLElement>('[role="alert"]') ?? [])].find((element) => element.offsetParent !== null);
    message?.scrollIntoView({ block: 'center', behavior: 'smooth' });
});
</script>

<template>
    <div ref="root" class="grid gap-4">
        <GeneralInfoCard :form="form" :options="options" />
        <Card>
            <Tabs v-model="active" :tabs="tabs">
                <template #general><GeneralTab :row="form" :error="error" :statuses="options.statuses" /></template>
                <template #price><PriceTab :row="form" :error="error" /></template>
                <template #inventory><InventoryTab :row="form" :error="error" /></template>
                <template #addons><AddonsTab :form="form" :addons="options.addons" :collapsed="collapsed" /></template>
                <template #variations><VariationsTab :form="form" :attributes="options.attributes" :categories="options.categories" /></template>
                <template #images><MediaTab :row="form" :error="error" :image-required="imageRequired" /></template>
                <template #seo><SeoTab :row="form" :error="error" /></template>
            </Tabs>
        </Card>
    </div>
</template>
