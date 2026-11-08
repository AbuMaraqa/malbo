<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { ExistingImage } from '@/Components/form/ImageUpload.vue';
import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';

import AppVersionsTab from './parts/AppVersionsTab.vue';
import GeneralTab from './parts/GeneralTab.vue';
import type { AppVersionForm, FontOption, SettingsForm } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    settings: { site_name: string; email_address_for_contact: string; site_description: string; dashboard_color: string; dashboard_font: string };
    colorPresets: string[];
    fonts: FontOption[];
    fontPreviewUrl: string;
    logo: ExistingImage[];
    appVersions: AppVersionForm[];
}>();

const form = useForm<SettingsForm>({
    _method: 'put',
    ...props.settings,
    logo: null,
    remove_logo: false,
    app_versions: props.appVersions.map((version) => ({ ...version })),
});

const active = ref('general');

const tabs = computed(() => {
    const keys = Object.keys(form.errors);

    return [
        { value: 'general', label: t('General Site Info'), hasError: keys.some((key) => !key.startsWith('app_versions')) },
        { value: 'app_versions', label: t('App Versions'), hasError: keys.some((key) => key.startsWith('app_versions')) },
    ];
});

function submit(): void {
    form.post(route('settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('logo', 'remove_logo'),
    });
}
</script>

<template>
    <Head><link rel="stylesheet" :href="fontPreviewUrl" /></Head>
    <PageHeader :title="t('Settings')" />
    <form @submit.prevent="submit">
        <Tabs v-model="active" :tabs="tabs">
            <template #general><GeneralTab :form="form" :logo="logo" :color-presets="colorPresets" :fonts="fonts" /></template>
            <template #app_versions><AppVersionsTab :form="form" :saved-count="appVersions.length" /></template>
        </Tabs>
        <FormActions v-if="can('Setting Update')" :processing="form.processing" />
    </form>
</template>
