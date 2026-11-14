<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed, defineAsyncComponent } from 'vue';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { CampaignBlockRow } from './parts/campaignBlocks';

// The block editor brings the rich text editor and the drag-and-drop list: loaded when shown.
const CampaignBlockEditor = defineAsyncComponent(() => import('./parts/CampaignBlockEditor.vue'));

export interface CampaignPageForm {
    title: string;
    seo_title: string;
    seo_description: string;
    slug: string;
    status: 'draft' | 'published';
    starts_at: string | null;
    ends_at: string | null;
    blocks: CampaignBlockRow[];
}

const props = defineProps<{ form: InertiaForm<CampaignPageForm>; blockTypes: Option[]; categories: Option[]; selectedProducts?: Option[] }>();

const statuses = computed<Option[]>(() => [
    { value: 'draft', label: t('Draft') },
    { value: 'published', label: t('Published') },
]);

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <Card>
                <TextInput v-model="form.title" :label="t('Title')" required :maxlength="160" :error="form.errors.title" />
            </Card>
            <Card :title="t('Blocks')">
                <CampaignBlockEditor v-model="form.blocks" :errors="errors" :block-types="blockTypes" :categories="categories" :selected-products="selectedProducts" />
            </Card>
        </div>

        <div class="space-y-4">
            <Card>
                <div class="space-y-4">
                    <SelectInput v-model="form.status" :label="t('Status')" :options="statuses" :error="form.errors.status" />
                    <TextInput v-model="form.starts_at" :label="t('Starts at')" type="datetime-local" :hint="t('Empty: as soon as it is published.')" :error="form.errors.starts_at" />
                    <TextInput v-model="form.ends_at" :label="t('Ends at')" type="datetime-local" :hint="t('Empty: until it is unpublished.')" :error="form.errors.ends_at" />
                    <TextInput
                        v-model="form.slug"
                        :label="t('Link')"
                        dir="ltr"
                        :maxlength="120"
                        :hint="t('Letters, numbers and dashes. Left empty, it is made from the title.')"
                        :error="form.errors.slug"
                    />
                </div>
            </Card>
            <Card :title="t('Search engines')">
                <div class="space-y-4">
                    <TextInput v-model="form.seo_title" :label="t('Search engine title')" :maxlength="160" :hint="t('Empty: the page title.')" :error="form.errors.seo_title" />
                    <TextareaInput v-model="form.seo_description" :label="t('Search engine description')" :rows="3" :maxlength="300" :error="form.errors.seo_description" />
                </div>
            </Card>
        </div>
    </div>
</template>
