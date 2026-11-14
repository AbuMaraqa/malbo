<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import CampaignPageFields, { type CampaignPageForm } from './CampaignPageFields.vue';
import { toRequestBlocks } from './parts/campaignBlocks';

defineOptions({ layout: AdminLayout });

defineProps<{ blockTypes: Option[]; categories: Option[] }>();

const form = useForm<CampaignPageForm>({
    title: '',
    seo_title: '',
    seo_description: '',
    slug: '',
    status: 'draft',
    starts_at: null,
    ends_at: null,
    blocks: [],
});

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({ ...data, blocks: toRequestBlocks((data as CampaignPageForm).blocks) })).post(route('campaign-pages.store'), { forceFormData: true });
}
</script>

<template>
    <PageHeader :title="t('Add campaign page')" :parents="[{ title: t('Campaign pages'), href: route('campaign-pages.index') }]" />
    <form @submit.prevent="submit">
        <CampaignPageFields :form="form" :block-types="blockTypes" :categories="categories" />
        <FormActions :processing="form.processing" :cancel-href="route('campaign-pages.index')" />
    </form>
</template>
