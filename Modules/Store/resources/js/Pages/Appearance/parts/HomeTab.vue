<script setup lang="ts">
import { t } from '@/lib/i18n';

import ImageField from './ImageField.vue';
import LocalizedField from './LocalizedField.vue';
import type { AppearanceForm, SavedImages } from './types';

defineProps<{ form: AppearanceForm; images: SavedImages }>();
</script>

<template>
    <div class="space-y-6">
        <LocalizedField v-model="form.hero_eyebrow" :form="form" name="hero_eyebrow" :label="t('Small heading')" />
        <LocalizedField v-model="form.hero_title" :form="form" name="hero_title" :label="t('Main title')" />
        <LocalizedField v-model="form.hero_subtitle" :form="form" name="hero_subtitle" :label="t('Subtitle')" textarea />
        <ImageField :form="form" field="hero_image" :label="t('Main image')" :images="images" />
    </div>
</template>
