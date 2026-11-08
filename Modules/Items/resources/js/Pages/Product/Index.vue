<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { DollarSign, QrCode } from '@/lib/icons';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import RecordActions from '../parts/RecordActions.vue';
import { useRecordDelete } from '../parts/useRecordDelete';
import BarcodeModal from './parts/BarcodeModal.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    image: string;
    name: string;
    slug: string;
    description: string | null;
    categories: string;
    status: number | null;
    is_active: boolean;
    base_price: string | number;
    sale_price: string | number | null;
    discount: number;
    locales: string[];
}

const props = defineProps<{ table: TablePayload<Row>; statuses: Option[]; categories: Option[]; brands: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'image', label: t('Image') },
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'slug', label: t('Slug'), sort: 'slug', toggleable: true },
    { key: 'categories', label: t('Category'), toggleable: true },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'is_active', label: t('Active'), sort: 'is_active' },
    { key: 'base_price', label: t('Base Price'), sort: 'base_price', class: 'tabular', toggleable: true },
    { key: 'sale_price', label: t('Sale Price'), sort: 'sale_price', class: 'tabular', toggleable: true },
    { key: 'discount', label: t('Discount'), sort: 'discount', class: 'tabular', toggleable: true, hidden: true },
    { key: 'locales', label: t('Locale'), toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'category_id', label: t('Category'), options: props.categories },
    { key: 'brand_id', label: t('Brand'), options: props.brands },
    { type: 'boolean', key: 'is_active', label: t('Active'), trueLabel: t('Active'), falseLabel: t('Inactive') },
    { type: 'boolean', key: 'is_featured', label: t('Is this product featured'), trueLabel: t('Yes'), falseLabel: t('No') },
    {
        key: 'stock_status',
        label: t('Stock Status'),
        options: [
            { value: 'in_stock', label: t('In Stock') },
            { value: 'out_of_stock', label: t('Out Of Stock') },
            { value: 'on_backorder', label: t('On Backorder') },
        ],
    },
    { type: 'number-range', key: 'base_price', label: t('Base Price') },
    { type: 'number-range', key: 'sale_price', label: t('Sale Price') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

const statusLabel = (value: number | null): string => props.statuses.find((status) => status.value === value)?.label ?? '';

function setStatus(row: Row, event: Event): void {
    router.put(route('products.status', row.id), { status: Number((event.target as HTMLSelectElement).value) }, { preserveScroll: true });
}

function setActive(row: Row, active: boolean): void {
    router.put(route('products.active', row.id), { active }, { preserveScroll: true });
}

const barcodeOpen = ref(false);
const barcodeRow = ref<Row | null>(null);

function openBarcodes(row: Row): void {
    barcodeRow.value = row;
    barcodeOpen.value = true;
}

const { remove, removeMany } = useRecordDelete('products');
</script>

<template>
    <PageHeader :title="t('Products List')">
        <template #actions>
            <Button v-if="can('Product Create')" variant="primary" :href="route('products.add')">{{ t('Add Product') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :selectable="can('Product Delete')" :empty-title="t('No products found')" :empty-text="t('Create a new product by clicking the button below')">
        <template #cell-image="{ row }">
            <img v-if="row.image" :src="row.image" alt="" class="size-12 rounded-control object-cover" />
        </template>
        <template #cell-name="{ row }">
            <Link v-if="can('Product Update')" :href="route('products.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-slug="{ row }">
            <span dir="ltr">{{ row.slug }}</span>
        </template>
        <template #cell-status="{ row }">
            <select
                v-if="can('Product Update')"
                :value="row.status ?? undefined"
                :aria-label="t('Status')"
                class="h-9 rounded-[10px] border border-gray-300 bg-card px-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                @change="setStatus(row, $event)"
            >
                <option v-for="status in statuses" :key="status.value" :value="status.value">{{ status.label }}</option>
            </select>
            <Badge v-else tone="brand">{{ statusLabel(row.status) }}</Badge>
        </template>
        <template #cell-is_active="{ row }">
            <Toggle :model-value="row.is_active" :disabled="!can('Product Update')" :aria-label="t('Active')" @update:model-value="setActive(row, !!$event)" />
        </template>
        <template #cell-locales="{ row }">
            <span class="text-muted uppercase">{{ row.locales.join(', ') }}</span>
        </template>
        <template #empty>
            <Button v-if="can('Product Create')" variant="primary" :href="route('products.add')">{{ t('Add Product') }}</Button>
        </template>
        <template #actions="{ row }">
            <Button variant="ghost" size="icon" :aria-label="t('Generate Barcode')" @click="openBarcodes(row)"><QrCode class="size-4" /></Button>
            <Button v-if="can('Product Branch Pricings')" variant="ghost" size="icon" :href="route('products.branch-pricings', row.id)" :aria-label="t('Branch Pricings')"><DollarSign class="size-4" /></Button>
            <RecordActions
                entity="Product"
                routes="products"
                :id="row.id"
                :title="row.name"
                :edit-href="route('products.edit', row.id)"
                :fields="[
                    { label: t('Name'), value: row.name },
                    { label: t('Slug'), value: row.slug },
                    { label: t('Description'), value: row.description },
                ]"
                @delete="remove(row.id, row.name)"
            />
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>

    <BarcodeModal v-model:open="barcodeOpen" :product-id="barcodeRow?.id ?? null" :title="barcodeRow?.name ?? ''" />
</template>
