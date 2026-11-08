<script setup lang="ts">
import type { Option } from '@/Components/form/SelectInput.vue';
import SelectInput from '@/Components/form/SelectInput.vue';
import { t } from '@/lib/i18n';

import ColorPicker from './ColorPicker.vue';
import ImageField from './ImageField.vue';
import LocalizedField from './LocalizedField.vue';
import type { AppearanceForm, SavedImages } from './types';

defineProps<{ form: AppearanceForm; images: SavedImages; presets: string[]; fonts: Option[] }>();
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 md:grid-cols-2">
            <ColorPicker v-model="form.primary_color" :presets="presets" :error="form.errors.primary_color" />
            <SelectInput v-model="form.font" :label="t('Font')" required :options="fonts" :error="form.errors.font" />
        </div>
        <div class="grid gap-6 md:grid-cols-2">
            <ImageField :form="form" field="logo" :label="t('Logo')" :images="images" />
            <ImageField
                :form="form"
                field="favicon"
                :label="t('Browser icon')"
                :images="images"
                accept="image/jpeg,image/png,image/webp,image/gif,image/x-icon,image/vnd.microsoft.icon"
            />
        </div>
        <LocalizedField v-model="form.announcement" :form="form" name="announcement" :label="t('Announcement bar')" />
    </div>
</template>
