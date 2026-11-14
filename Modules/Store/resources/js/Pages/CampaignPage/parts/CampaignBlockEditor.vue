<script setup lang="ts">
/**
 * A campaign page's blocks: folded rows that open to their fields, dragged into order (Repeater
 * loads Sortable only when it mounts), removed, and added by type. Loaded with
 * defineAsyncComponent by the page's form: it brings the rich text editor along.
 */
import { computed, ref, type Component } from 'vue';

import Repeater from '@/Components/form/Repeater.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { Plus } from '@/lib/icons';

import BannerBlockFields from './blocks/BannerBlockFields.vue';
import CountdownBlockFields from './blocks/CountdownBlockFields.vue';
import CtaBlockFields from './blocks/CtaBlockFields.vue';
import ImageBlockFields from './blocks/ImageBlockFields.vue';
import InstagramBlockFields from './blocks/InstagramBlockFields.vue';
import ProductsBlockFields from './blocks/ProductsBlockFields.vue';
import TextBlockFields from './blocks/TextBlockFields.vue';
import { blockLabel, newBlock, type CampaignBlockRow, type CampaignBlockType } from './campaignBlocks';

const props = defineProps<{
    errors?: Record<string, string | undefined>;
    blockTypes: Option[];
    categories: Option[];
    selectedProducts?: Option[];
}>();

const blocks = defineModel<CampaignBlockRow[]>({ required: true });

const FIELDS: Record<CampaignBlockType, Component> = {
    text: TextBlockFields,
    image: ImageBlockFields,
    banner: BannerBlockFields,
    products: ProductsBlockFields,
    cta: CtaBlockFields,
    countdown: CountdownBlockFields,
    instagram: InstagramBlockFields,
};

const adding = ref<CampaignBlockType>((props.blockTypes[0]?.value as CampaignBlockType) ?? 'text');
const typeLabels = computed(() => Object.fromEntries(props.blockTypes.map((type) => [type.value, type.label])));

function add(): void {
    blocks.value = [...blocks.value, newBlock(adding.value)];
}

function label(block: CampaignBlockRow): string {
    return blockLabel(block, typeLabels.value[block.type] ?? block.type);
}
</script>

<template>
    <div>
        <!-- The Repeater's own button would add an untyped block: blocks are added by type below. -->
        <Repeater v-model="blocks" name="blocks" :errors="errors" :new-item="() => newBlock(adding)" :max="0" collapsible :item-label="label" :empty-text="t('No blocks yet: add the first one below.')">
            <template #item="{ item, error }">
                <component :is="FIELDS[item.type]" :block="item" :error="error" v-bind="item.type === 'products' ? { categories, selectedProducts } : {}" />
            </template>
        </Repeater>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <SelectInput v-model="adding" class="min-w-48" :label="t('New block')" :options="blockTypes" />
            <Button data-add-block @click="add"><Plus class="size-4" />{{ t('Add block') }}</Button>
        </div>
    </div>
</template>
