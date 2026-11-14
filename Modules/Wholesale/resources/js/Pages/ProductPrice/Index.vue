<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { Tag } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    sku: string | null;
    categories: string;
    wholesale_price: string | null;
    variations_count: number;
    by_piece: boolean;
}

const props = defineProps<{ table: TablePayload<Row>; categories: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'sku', label: t('SKU'), toggleable: true },
    { key: 'categories', label: t('Category'), toggleable: true },
    { key: 'wholesale_price', label: t('Wholesale Price'), sort: 'wholesale_price', class: 'tabular' },
    { key: 'variations_count', label: t('Variants'), class: 'tabular', toggleable: true },
    { key: 'by_piece', label: t('Sold by the piece') },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { key: 'category_id', label: t('Category'), options: props.categories },
    { type: 'boolean', key: 'has_wholesale_price', label: t('Has a wholesale price'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'has_breaks', label: t('Has quantity breaks'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'wholesale_by_piece', label: t('Sold by the piece'), trueLabel: t('By the piece'), falseLabel: t('In size runs') },
]);
</script>

<template>
    <PageHeader
        :title="t('Wholesale prices')"
        :count="table.meta.total"
        :description="t('The base wholesale price is set in the product form; here, each tier\'s own price, quantity breaks and size runs.')"
    />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No products found')">
        <template #cell-name="{ row }">
            <Link v-if="can('Wholesale Price Update')" :href="route('wholesale-prices.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-sku="{ row }">
            <span dir="ltr">{{ row.sku }}</span>
        </template>
        <template #cell-wholesale_price="{ row }">
            <span v-if="row.wholesale_price">{{ row.wholesale_price }}</span>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-by_piece="{ row }">
            <Badge :tone="row.by_piece ? 'neutral' : 'info'">{{ row.by_piece ? t('By the piece') : t('In size runs') }}</Badge>
        </template>
        <template #actions="{ row }">
            <RowMenu v-if="can('Wholesale Price Update')">
                <DropdownItem :href="route('wholesale-prices.edit', row.id)"><Tag />{{ t('Wholesale prices') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
