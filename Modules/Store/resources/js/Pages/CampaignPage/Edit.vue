<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { ExternalLink } from '@/lib/icons';
import { route } from '@/lib/route';

import CampaignPageFields, { type CampaignPageForm } from './CampaignPageFields.vue';
import { toRequestBlocks, type CampaignBlockSettings, type CampaignBlockType } from './parts/campaignBlocks';

defineOptions({ layout: AdminLayout });

interface SavedBlock {
    id: number;
    type: CampaignBlockType;
    settings: CampaignBlockSettings;
    content: Record<string, string>;
    image: string | null;
}

const props = defineProps<{
    page: Omit<CampaignPageForm, 'blocks'> & { id: number; blocks: SavedBlock[] };
    blockTypes: Option[];
    categories: Option[];
    selectedProducts: Option[];
    previewUrl: string;
}>();

const { id: pageId, blocks, ...values } = props.page;

const form = useForm<CampaignPageForm>({
    ...values,
    blocks: blocks.map((block) => ({
        id: block.id,
        type: block.type,
        settings: { ...block.settings },
        content: { ...block.content },
        image: null,
        image_url: block.image,
    })),
});

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({ ...data, blocks: toRequestBlocks((data as CampaignPageForm).blocks), _method: 'put' })).post(route('campaign-pages.update', pageId), {
        forceFormData: true,
    });
}
</script>

<template>
    <PageHeader :title="t('Edit campaign page')" :parents="[{ title: t('Campaign pages'), href: route('campaign-pages.index') }]">
        <template #actions>
            <a :href="previewUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline dark:text-brand-400">
                <ExternalLink class="size-4" />
                {{ t('Preview') }}
            </a>
        </template>
    </PageHeader>
    <form @submit.prevent="submit">
        <CampaignPageFields :form="form" :block-types="blockTypes" :categories="categories" :selected-products="selectedProducts" />
        <FormActions :processing="form.processing" :cancel-href="route('campaign-pages.index')" />
    </form>
</template>
