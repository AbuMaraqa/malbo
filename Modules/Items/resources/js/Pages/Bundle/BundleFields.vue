<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import ImageUpload from '@/Components/form/ImageUpload.vue';
import MultiSelect from '@/Components/form/MultiSelect.vue';
import RichEditor from '@/Components/form/RichEditor.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import BundleComponentsField from './parts/BundleComponentsField.vue';
import type { BundleForm, BundleFormOptions, BundleImages } from './parts/types';

const props = defineProps<{ form: InertiaForm<BundleForm>; options: BundleFormOptions; saved?: BundleImages; selectedComponents?: Option[] }>();

const pricingOptions = computed<Option[]>(() => [
    { value: 'fixed', label: t('Its own price') },
    { value: 'percent', label: t('A percentage off the components') },
]);

/** The saved gallery in the order the user dragged it to. */
const galleryImages = computed(() => {
    const order = props.form.gallery_order;
    const images = props.saved?.gallery_images ?? [];

    return order.length ? [...images].sort((a, b) => order.indexOf(a.id) - order.indexOf(b.id)) : images;
});
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <Card>
                <div class="space-y-4">
                    <SelectInput v-model="form.bundle_type" :label="t('Type')" :options="options.types" :hint="t('A set is one outfit at its own price; a bundle offer is separate products sold together.')" :error="form.errors.bundle_type" />
                    <TextInput v-model="form.name" :label="t('Name')" required :maxlength="255" :error="form.errors.name" />
                    <TextareaInput v-model="form.short_description" :label="t('Short description')" :rows="2" :maxlength="500" :error="form.errors.short_description" />
                    <RichEditor v-model="form.description" :label="t('Description')" :error="form.errors.description" />
                </div>
            </Card>

            <Card :title="t('Components')">
                <BundleComponentsField :form="form" :selected="selectedComponents" />
            </Card>

            <Card :title="t('Price')">
                <div class="grid gap-4 md:grid-cols-3">
                    <SelectInput v-model="form.pricing" :label="t('Pricing')" :options="pricingOptions" :error="form.errors.pricing" />
                    <template v-if="form.pricing === 'fixed'">
                        <TextInput v-model="form.base_price" :label="t('Price')" type="number" step="0.01" min="0" required :error="form.errors.base_price" />
                        <TextInput v-model="form.sale_price" :label="t('Sale price')" type="number" step="0.01" min="0" :error="form.errors.sale_price" />
                    </template>
                    <TextInput
                        v-else
                        v-model="form.bundle_discount_percent"
                        :label="t('Discount %')"
                        type="number"
                        step="0.01"
                        min="1"
                        max="90"
                        required
                        :hint="t('Off the regular prices of the components; it follows them when they change.')"
                        :error="form.errors.bundle_discount_percent"
                    />
                </div>
            </Card>

            <Card :title="t('Images')">
                <div class="grid gap-6 md:grid-cols-2">
                    <ImageUpload v-model="form.image" v-model:removed="form.removed_image" :existing="saved?.images" :label="t('Image')" :error="form.errors.image" />
                    <ImageUpload
                        v-model="form.gallery"
                        v-model:removed="form.removed_gallery"
                        v-model:order="form.gallery_order"
                        multiple
                        reorderable
                        :existing="galleryImages"
                        :label="t('Gallery')"
                        :error="form.errors.gallery"
                    />
                </div>
            </Card>
        </div>

        <Card>
            <div class="space-y-4">
                <SelectInput v-model="form.status" :label="t('Status')" :options="options.statuses" :error="form.errors.status" />
                <Toggle v-model="form.is_active" :label="t('Active')" />
                <Toggle v-model="form.is_new" :label="t('New')" />
                <Toggle v-model="form.is_featured" :label="t('Featured')" />
                <MultiSelect v-model="form.category_id" :label="t('Category')" :options="options.categories" :error="form.errors.category_id" />
                <TextInput v-model="form.slug" :label="t('Link')" dir="ltr" :maxlength="255" :hint="t('Letters, numbers and dashes. Left empty, it is made from the name.')" :error="form.errors.slug" />
                <TextInput v-model="form.sku" :label="t('SKU')" dir="ltr" :maxlength="100" :error="form.errors.sku" />
                <TextInput
                    v-model="form.max_quantity_per_order"
                    :label="t('Max quantity per order')"
                    type="number"
                    min="1"
                    max="1000"
                    :error="form.errors.max_quantity_per_order"
                />
                <p class="text-sm text-muted">{{ t('A set or bundle has no stock of its own: what can be sold comes from its components.') }}</p>
            </div>
        </Card>
    </div>
</template>
