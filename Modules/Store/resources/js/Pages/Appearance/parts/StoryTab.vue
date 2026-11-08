<script setup lang="ts">
import { computed } from 'vue';

import Repeater from '@/Components/form/Repeater.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';

import ImageField from './ImageField.vue';
import LocalizedField from './LocalizedField.vue';
import type { AppearanceForm, BrandValue, SavedImages } from './types';

const props = defineProps<{ form: AppearanceForm; images: SavedImages; maxValues: number }>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
const atLimit = computed(() => props.form.values.length >= props.maxValues);

function newValue(): BrandValue {
    return { title: { ar: '', en: '' }, text: { ar: '', en: '' } };
}
</script>

<template>
    <div class="space-y-6">
        <LocalizedField v-model="form.story_title" :form="form" name="story_title" :label="t('Story title')" />
        <LocalizedField v-model="form.story_text" :form="form" name="story_text" :label="t('Story')" textarea />
        <ImageField :form="form" field="story_image" :label="t('Story image')" :images="images" />

        <div>
            <Repeater
                v-model="form.values"
                name="values"
                :errors="errors"
                :new-item="newValue"
                :label="t('Brand values')"
                :add-label="t('Add value')"
                :max="maxValues"
            >
                <template #item="{ item, error }">
                    <div class="grid gap-4 md:grid-cols-2">
                        <TextInput v-model="item.title.ar" :label="t('Title (Arabic)')" :maxlength="80" :error="error('title.ar')" />
                        <TextInput v-model="item.title.en" :label="t('Title (English)')" :maxlength="80" :error="error('title.en')" />
                        <TextareaInput v-model="item.text.ar" :label="t('Text (Arabic)')" :rows="2" :maxlength="300" :error="error('text.ar')" />
                        <TextareaInput v-model="item.text.en" :label="t('Text (English)')" :rows="2" :maxlength="300" :error="error('text.en')" />
                    </div>
                </template>
            </Repeater>
            <p v-if="atLimit" class="mt-2 text-sm text-muted">{{ t('Up to :max values.', { max: maxValues }) }}</p>
        </div>
    </div>
</template>
