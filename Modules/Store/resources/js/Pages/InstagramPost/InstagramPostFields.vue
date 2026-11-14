<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import RemoteMultiSelect from '@/Components/form/RemoteMultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

export interface InstagramPostForm {
    permalink: string;
    caption: string;
    posted_at: string | null;
    sort_order: number | string;
    is_active: boolean;
    products: (number | string)[];
    image: File | null;
}

defineProps<{ form: InertiaForm<InstagramPostForm>; existing?: ExistingImage[]; selectedProducts?: Option[] }>();
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
            <div class="space-y-4">
                <TextInput
                    v-model="form.permalink"
                    :label="t('Link to the post')"
                    dir="ltr"
                    required
                    :maxlength="500"
                    placeholder="https://www.instagram.com/p/..."
                    :error="form.errors.permalink"
                />
                <TextareaInput v-model="form.caption" :label="t('Caption')" :rows="4" :maxlength="2200" :error="form.errors.caption" />
                <RemoteMultiSelect
                    v-model="form.products"
                    :url="route('instagram-posts.products')"
                    :initial="selectedProducts"
                    :label="t('Products in the post')"
                    :hint="t('Shop the look: up to 6 products shown with the picture.')"
                    :error="form.errors.products"
                />
            </div>
        </Card>
        <Card>
            <div class="space-y-4">
                <ImageUpload v-model="form.image" :existing="existing" :label="t('Picture')" :required="!existing?.length" :error="form.errors.image" />
                <TextInput v-model="form.posted_at" :label="t('Posted on')" type="datetime-local" :error="form.errors.posted_at" />
                <TextInput v-model="form.sort_order" :label="t('Sort Order')" type="number" min="0" max="10000" :error="form.errors.sort_order" />
                <Toggle v-model="form.is_active" :label="t('Shown in the store')" />
            </div>
        </Card>
    </div>
</template>
