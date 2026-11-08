<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import ImageUpload, { type ExistingImage } from '@/Components/form/ImageUpload.vue';
import MultiSelect from '@/Components/form/MultiSelect.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface OfferForm {
    name: string;
    description: string;
    /** Add: several, one offer each. Edit: one. */
    branch_id: (number | string)[] | number | string | null;
    offerable_model: string | null;
    offerable_id: number | string | null;
    type: number;
    value: number | string;
    code: string | null;
    min_purchase_amount: number | string;
    usage_limit: number | string;
    usage_limit_per_user: number | string;
    start_date: string | null;
    end_date: string | null;
    is_active: boolean;
    image: File | File[] | null;
    removed_image: (number | string)[];
}

export interface ProductChoice extends Option {
    branches: number[];
}

const props = defineProps<{
    form: InertiaForm<OfferForm>;
    types: Option[];
    branches: Option[];
    categories: Option[];
    products: ProductChoice[];
    images?: ExistingImage[];
    /** Add makes one offer per branch. */
    manyBranches?: boolean;
}>();

const targetModels = computed<Option[]>(() => [
    { value: 'items_category', label: t('Category') },
    { value: 'items_product', label: t('Product') },
]);

const chosenBranches = computed(() => (Array.isArray(props.form.branch_id) ? props.form.branch_id : [props.form.branch_id]).filter(Boolean).map(Number));

/** A product is offered in the branches that sell it. */
const targets = computed<Option[]>(() => {
    if (props.form.offerable_model === 'items_category') return props.categories;
    if (props.form.offerable_model !== 'items_product') return [];

    return props.products.filter((product) => product.branches.some((branch) => chosenBranches.value.includes(branch)));
});

function chooseModel(value: string | number | null | undefined): void {
    props.form.offerable_model = value ? String(value) : null;
    props.form.offerable_id = null;
}

function chooseBranches(value: unknown): void {
    props.form.branch_id = value as OfferForm['branch_id'];

    // The chosen product may not be sold in the new branches.
    if (props.form.offerable_model === 'items_product') props.form.offerable_id = null;
}

const branchError = computed(() => {
    const errors = props.form.errors as Record<string, string | undefined>;
    const key = Object.keys(errors).find((item) => item === 'branch_id' || item.startsWith('branch_id.'));

    return key ? errors[key] : undefined;
});
</script>

<template>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" :label="t('Name')" required :error="form.errors.name" />
                <MultiSelect :model-value="form.branch_id" :single="!manyBranches" :label="t('Branch')" required :options="branches" :error="branchError" @update:model-value="chooseBranches" />
                <SelectInput :model-value="form.offerable_model" :label="t('Offerable Model')" required :placeholder="t('Select')" :options="targetModels" :error="form.errors.offerable_model" @update:model-value="chooseModel" />
                <MultiSelect v-if="form.offerable_model" v-model="form.offerable_id" single :label="t('Offerable ID')" required :options="targets" :error="form.errors.offerable_id" />
                <SelectInput v-model="form.type" :label="t('Type')" required :options="types" :error="form.errors.type" />
                <TextInput v-model="form.value" :label="t('Value')" type="number" step="0.01" min="0" required :error="form.errors.value" />
                <TextInput v-model="form.code" class="md:col-span-2" :label="t('Code')" dir="ltr" :maxlength="50" :error="form.errors.code" />
                <TextInput v-model="form.min_purchase_amount" class="md:col-span-2" :label="t('Min Purchase Amount')" type="number" step="0.01" min="0" required :error="form.errors.min_purchase_amount" />
                <TextInput v-model="form.usage_limit" :label="t('Usage Limit')" type="number" min="1" required :error="form.errors.usage_limit" />
                <TextInput v-model="form.usage_limit_per_user" :label="t('Usage Limit Per User')" type="number" min="1" required :error="form.errors.usage_limit_per_user" />
                <TextInput v-model="form.start_date" :label="t('Start Date')" type="datetime-local" :error="form.errors.start_date" />
                <TextInput v-model="form.end_date" :label="t('End Date')" type="datetime-local" :error="form.errors.end_date" />
                <TextareaInput v-model="form.description" class="md:col-span-2" :label="t('Description')" :maxlength="255" :error="form.errors.description" />
                <Toggle v-model="form.is_active" :label="t('Active')" />
            </div>
        </Card>
        <Card>
            <ImageUpload v-model="form.image" v-model:removed="form.removed_image" :existing="images" :label="t('Image')" required :error="form.errors.image" />
        </Card>
    </div>
</template>
