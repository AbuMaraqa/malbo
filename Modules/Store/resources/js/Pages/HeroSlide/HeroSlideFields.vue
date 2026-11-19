<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import TextInput from '@/Components/form/TextInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface HeroSlideForm {
    eyebrow: string;
    title: string;
    subtitle: string;
    button_label: string;
    link: string;
    sort_order: number | string;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    image: File | null;
    mobile_image: File | null;
    /** Saved phones' pictures the user removed (ImageUpload). */
    mobile_removed: (number | string)[];
}

defineProps<{ form: InertiaForm<HeroSlideForm>; existing?: ExistingImage[]; existingMobile?: ExistingImage[] }>();

const accept = 'image/jpeg,image/png,image/webp';
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <Card :title="t('Pictures')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <ImageUpload
                        v-model="form.image"
                        :existing="existing"
                        :accept="accept"
                        :label="t('Picture')"
                        :hint="t('Wide, about 1920 × 900 pixels. Keep the important part in the middle.')"
                        :required="!existing?.length"
                        :error="form.errors.image"
                    />
                    <ImageUpload
                        v-model="form.mobile_image"
                        v-model:removed="form.mobile_removed"
                        :existing="existingMobile"
                        :accept="accept"
                        :label="t('Picture for phones')"
                        :hint="t('Optional, upright, about 1080 × 1350 pixels. Without it, phones show the wide picture.')"
                        :error="form.errors.mobile_image"
                    />
                </div>
            </Card>

            <Card :title="t('Texts')" :description="t('In the language you are using. All optional: leave them empty when the text is already in the picture.')">
                <div class="space-y-4">
                    <TextInput v-model="form.eyebrow" :label="t('Small heading')" :maxlength="120" :placeholder="t('New collection')" :error="form.errors.eyebrow" />
                    <TextInput v-model="form.title" :label="t('Title')" :maxlength="160" :error="form.errors.title" />
                    <TextareaInput v-model="form.subtitle" :label="t('Subtitle')" :rows="2" :maxlength="300" :error="form.errors.subtitle" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <TextInput
                            v-model="form.link"
                            :label="t('Link')"
                            dir="ltr"
                            :maxlength="500"
                            placeholder="/ar/shop"
                            :hint="t('A page of the store (like /ar/shop) or an https:// address.')"
                            :error="form.errors.link"
                        />
                        <TextInput
                            v-model="form.button_label"
                            :label="t('Button text')"
                            :maxlength="60"
                            :placeholder="t('Shop now')"
                            :hint="t('Shown when the slide has texts and a link.')"
                            :error="form.errors.button_label"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <Card :title="t('Display')" class="self-start">
            <div class="space-y-4">
                <Toggle v-model="form.is_active" :label="t('Shown in the store')" />
                <TextInput v-model="form.sort_order" :label="t('Sort Order')" type="number" min="0" max="10000" :error="form.errors.sort_order" />
                <TextInput v-model="form.starts_at" :label="t('Starts at')" type="datetime-local" :hint="t('Optional.')" :error="form.errors.starts_at" />
                <TextInput v-model="form.ends_at" :label="t('Ends at')" type="datetime-local" :hint="t('Optional.')" :error="form.errors.ends_at" />
            </div>
        </Card>
    </div>
</template>
