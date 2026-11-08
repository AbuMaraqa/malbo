<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import FormActions from '@/Components/form/FormActions.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { ExternalLink } from '@/lib/icons';
import { route } from '@/lib/route';

import BrandTab from './parts/BrandTab.vue';
import ContactTab from './parts/ContactTab.vue';
import HomeTab from './parts/HomeTab.vue';
import MembersTab from './parts/MembersTab.vue';
import StoryTab from './parts/StoryTab.vue';
import type { AppearanceFormData, AppearanceSettings, SavedImages } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    settings: AppearanceSettings;
    images: SavedImages;
    presets: string[];
    fonts: Option[];
    maxValues: number;
    storeUrl: string;
}>();

const form = useForm<AppearanceFormData>({
    ...props.settings,
    _method: 'put',
    logo: null,
    favicon: null,
    hero_image: null,
    story_image: null,
    removed_images: [],
});

/** Which fields live on which tab, to mark a tab that has an error. */
const FIELDS: Record<string, RegExp> = {
    brand: /^(primary_color|font|logo|favicon|announcement)/,
    home: /^(hero_)/,
    story: /^(story_|values)/,
    members: /^membership_/,
    contact: /^(whatsapp|phone|email|address|instagram_url|facebook_url|tiktok_url|x_url)/,
};

const tabs = computed(() => {
    const failed = Object.keys(form.errors);
    const tab = (value: string, label: string) => ({ value, label, hasError: failed.some((field) => FIELDS[value].test(field)) });

    return [tab('brand', t('Brand identity')), tab('home', t('Home page')), tab('story', t('Story & values')), tab('members', t('Members')), tab('contact', t('Contact'))];
});

function save(): void {
    form.post(route('appearance.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('logo', 'favicon', 'hero_image', 'story_image', 'removed_images'),
    });
}
</script>

<template>
    <PageHeader :title="t('Store appearance')">
        <template #actions>
            <a :href="storeUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline dark:text-brand-400">
                <ExternalLink class="size-4" />
                {{ t('View store') }}
            </a>
        </template>
    </PageHeader>

    <form @submit.prevent="save">
        <Card>
            <Tabs :tabs="tabs">
                <template #brand><BrandTab :form="form" :images="images" :presets="presets" :fonts="fonts" /></template>
                <template #home><HomeTab :form="form" :images="images" /></template>
                <template #story><StoryTab :form="form" :images="images" :max-values="maxValues" /></template>
                <template #members><MembersTab :form="form" /></template>
                <template #contact><ContactTab :form="form" /></template>
            </Tabs>
        </Card>
        <FormActions :processing="form.processing" />
    </form>
</template>
