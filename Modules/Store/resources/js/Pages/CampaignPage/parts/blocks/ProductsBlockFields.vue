<script setup lang="ts">
import { computed } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import RemoteMultiSelect from '@/Components/form/RemoteMultiSelect.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { CampaignBlockRow } from '../campaignBlocks';

defineProps<{ block: CampaignBlockRow; error: (field: string) => string | undefined; categories: Option[]; selectedProducts?: Option[] }>();

const sources = computed<Option[]>(() => [
    { value: 'picked', label: t('Products I pick') },
    { value: 'category', label: t('A category') },
]);

</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <TextInput v-model="block.content.heading" :label="t('Heading')" :maxlength="160" :error="error('content.heading')" />
        <SelectInput v-model="block.settings.source" :label="t('Products from')" :options="sources" :error="error('settings.source')" />
        <RemoteMultiSelect
            v-if="block.settings.source !== 'category'"
            v-model="block.settings.product_ids"
            class="md:col-span-2"
            :url="route('campaign-pages.products')"
            :initial="selectedProducts"
            :label="t('Products')"
            :hint="t('In the order you pick them; sets and bundles too. Hidden products are left out.')"
            :error="error('settings.product_ids')"
        />
        <MultiSelect v-else v-model="block.settings.category_id" single :label="t('Category')" :options="categories" :error="error('settings.category_id')" />
        <TextInput v-model="block.settings.limit" :label="t('Products shown')" type="number" min="1" max="24" :error="error('settings.limit')" />
    </div>
</template>
