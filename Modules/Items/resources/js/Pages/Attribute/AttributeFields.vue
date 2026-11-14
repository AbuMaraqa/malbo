<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import ColorInput from '@/Components/form/ColorInput.vue';
import Repeater from '@/Components/form/Repeater.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface AttributeValueRow {
    id?: number;
    name: string;
    description: string;
    color_code: string;
}

export interface AttributeForm {
    name: string;
    slug: string;
    type: number;
    is_filterable: boolean;
    is_size: boolean;
    description: string;
    values: AttributeValueRow[];
}

defineProps<{ form: InertiaForm<AttributeForm>; types: Option[]; colorType: number }>();

/** A drag, for the page that saves the order at once. */
defineEmits<{ reorder: [rows: AttributeValueRow[]] }>();

const newValue = (): AttributeValueRow => ({ name: '', description: '', color_code: '#000000' });
</script>

<template>
    <div class="grid max-w-3xl gap-4">
        <Card>
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
                <TextInput v-model="form.slug" :label="t('Slug')" dir="ltr" :error="form.errors.slug" :hint="t('Leave empty to make it from the name')" />
                <SelectInput v-model="form.type" class="md:col-span-2" :label="t('Attribute Type')" required :options="types" :error="form.errors.type" />
                <TextareaInput v-model="form.description" class="md:col-span-2" :label="t('Description')" :error="form.errors.description" />
                <Toggle v-model="form.is_filterable" class="md:col-span-2" :label="t('Show as a filter in the shop')" :hint="t('Buyers can narrow the shop by its values, like color or size.')" />
                <p v-if="form.errors.is_filterable" class="text-sm text-error-600 md:col-span-2 dark:text-error-400">{{ form.errors.is_filterable }}</p>
                <Toggle
                    v-model="form.is_size"
                    class="md:col-span-2"
                    :label="t('This is the size (shown on product cards and used for the size guide)')"
                    :hint="t('Only one attribute is the size: marking this one unmarks the other, and makes it a filter.')"
                    @update:model-value="(value) => value && (form.is_filterable = true)"
                />
                <p v-if="form.errors.is_size" class="text-sm text-error-600 md:col-span-2 dark:text-error-400">{{ form.errors.is_size }}</p>
            </div>
        </Card>
        <Card id="values" :title="t('Attribute values')">
            <Repeater
                v-model="form.values"
                name="values"
                :errors="form.errors"
                :new-item="newValue"
                :add-label="t('Add Attribute Value')"
                :empty-text="t('No values yet')"
                :item-label="(row) => row.name || t('Untitled')"
                cloneable
                collapsible
                @reorder="$emit('reorder', $event)"
            >
                <template #item="{ item, error }">
                    <div class="grid gap-3">
                        <TextInput v-model="item.name" :label="t('Name')" required :error="error('name')" />
                        <ColorInput v-if="form.type === colorType" v-model="item.color_code" :label="t('Color')" :error="error('color_code')" />
                        <TextareaInput v-model="item.description" :label="t('Description')" :error="error('description')" />
                    </div>
                </template>
            </Repeater>
        </Card>
    </div>
</template>
