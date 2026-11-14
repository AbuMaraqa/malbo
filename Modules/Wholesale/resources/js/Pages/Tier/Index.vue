<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import MinimumOrderCard from './MinimumOrderCard.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    discount_percent: number;
    is_default: boolean;
    merchants_count: number;
    sort_order: number;
}

defineProps<{ table: TablePayload<Row>; settings: { minimum_order_value: number | null; minimum_order_pieces: number | null } }>();

const page = usePage();
/** The default tier cannot be deleted: the message comes back on `tier`. */
const tierError = computed(() => (page.props.errors as Record<string, string>).tier ?? null);

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'discount_percent', label: t('Discount off the wholesale price (%)'), sort: 'discount_percent', class: 'tabular' },
    { key: 'merchants_count', label: t('Merchants'), sort: 'merchants_count', class: 'tabular' },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { type: 'boolean', key: 'is_default', label: t('Default tier'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'number-range', key: 'discount_percent', label: t('Discount (%)') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('Its merchants move to the default tier.') })) {
        router.delete(route('wholesale-tiers.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Price tiers')" :description="t('A tier is a discount off the wholesale price. A product can also have its own price per tier.')">
        <template #actions>
            <Button v-if="can('Wholesale Tier Create')" variant="primary" :href="route('wholesale-tiers.add')">{{ t('Add price tier') }}</Button>
        </template>
    </PageHeader>

    <p v-if="tierError" role="alert" class="mb-4 rounded-control border border-danger px-4 py-3 text-sm text-danger">{{ tierError }}</p>

    <MinimumOrderCard v-if="can('Wholesale Tier Update')" :settings="settings" class="mb-4" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No price tiers yet')" :empty-text="t('Without tiers every merchant pays the wholesale price.')">
        <template #cell-name="{ row }">
            <Link v-if="can('Wholesale Tier Update')" :href="route('wholesale-tiers.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
            <Badge v-if="row.is_default" tone="brand" class="ms-2">{{ t('Default') }}</Badge>
        </template>
        <template #cell-discount_percent="{ row }">{{ row.discount_percent }}%</template>
        <template #actions="{ row }">
            <RowMenu v-if="can('Wholesale Tier Update') || can('Wholesale Tier Delete')">
                <DropdownItem v-if="can('Wholesale Tier Update')" :href="route('wholesale-tiers.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <template v-if="can('Wholesale Tier Delete') && !row.is_default">
                    <DropdownSeparator />
                    <DropdownItem destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
                </template>
            </RowMenu>
        </template>
    </DataTable>
</template>
