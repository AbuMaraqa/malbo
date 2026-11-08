<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import Repeater from '@/Components/form/Repeater.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface AddonOptionRow {
    id?: number;
    name: string;
    price: number | string;
    is_default: boolean;
    is_quantifiable: boolean;
    description: string;
    image: File | File[] | null;
    removed_image: (number | string)[];
    /** The saved image, shown only: the server ignores it. */
    images: ExistingImage[];
}

export interface AddonForm {
    name: string;
    type: number;
    description: string;
    options: AddonOptionRow[];
}

defineProps<{ form: InertiaForm<AddonForm>; types: Option[]; collapsed?: boolean }>();

const newOption = (): AddonOptionRow => ({
    name: '',
    price: '',
    is_default: false,
    is_quantifiable: false,
    description: '',
    image: null,
    removed_image: [],
    images: [],
});
</script>

<template>
    <div class="grid max-w-3xl gap-4">
        <Card>
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
                <SelectInput v-model="form.type" :label="t('Type')" required :options="types" :error="form.errors.type" />
                <TextareaInput v-model="form.description" class="md:col-span-2" :label="t('Description')" :error="form.errors.description" />
            </div>
        </Card>
        <Card :title="t('Options')">
            <Repeater
                v-model="form.options"
                name="options"
                :errors="form.errors"
                :new-item="newOption"
                :add-label="t('Add Option')"
                :empty-text="t('No options yet')"
                :item-label="(row) => row.name || t('New Option')"
                :reorderable="false"
                collapsible
                :collapsed="collapsed"
            >
                <template #item="{ item, error }">
                    <div class="grid gap-3 md:grid-cols-2">
                        <TextInput v-model="item.name" :label="t('Name')" required :error="error('name')" />
                        <TextInput v-model="item.price" :label="t('Price')" type="number" step="0.01" min="0" required :error="error('price')" />
                        <Toggle v-model="item.is_default" :label="t('Is Default')" />
                        <Toggle v-model="item.is_quantifiable" :label="t('Is Quantifiable')" />
                        <TextareaInput v-model="item.description" class="md:col-span-2" :label="t('Description')" :error="error('description')" />
                        <ImageUpload v-model="item.image" v-model:removed="item.removed_image" class="md:col-span-2" :existing="item.images" :label="t('Image')" required :error="error('image')" />
                    </div>
                </template>
            </Repeater>
        </Card>
    </div>
</template>
