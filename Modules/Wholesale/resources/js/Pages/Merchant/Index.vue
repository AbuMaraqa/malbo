<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { can, t } from '@/lib/i18n';
import { Eye } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    shop_name: string;
    owner_name: string;
    phone: string;
    email: string;
    city: string | null;
    status: string;
    status_label: string;
    status_tone: 'neutral' | 'success' | 'warning' | 'danger';
    tier: string | null;
    sales_rep: string | null;
    created_at: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; statuses: Option[]; tiers: Option[]; salesReps: Option[]; cities: Option[] }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'shop_name', label: t('Shop name'), sort: 'shop_name' },
    { key: 'owner_name', label: t('Owner'), sort: 'owner_name', toggleable: true },
    { key: 'phone', label: t('Phone'), toggleable: true },
    { key: 'city', label: t('City'), toggleable: true },
    { key: 'status', label: t('Status'), sort: 'status' },
    { key: 'tier', label: t('Price tier'), toggleable: true },
    { key: 'sales_rep', label: t('Sales rep'), toggleable: true },
    { key: 'created_at', label: t('Applied on'), sort: 'created_at', toggleable: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'status', label: t('Status'), options: props.statuses },
    { key: 'tier_id', label: t('Price tier'), options: [{ value: 'none', label: t('No tier (default)') }, ...props.tiers] },
    { key: 'sales_rep_id', label: t('Sales rep'), options: props.salesReps },
    { key: 'city_id', label: t('City'), options: props.cities },
    { type: 'text', key: 'shop_name', label: t('Shop name'), placeholder: t('Search by name') },
    { type: 'date-range', key: 'created_at', label: t('Applied on') },
]);
</script>

<template>
    <PageHeader :title="t('Merchants')" :count="table.meta.total">
        <template #actions>
            <Button v-if="can('Merchant Create')" variant="primary" :href="route('merchants.add')">{{ t('Add merchant') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No merchants yet')" :empty-text="t('Shops apply on the wholesale join form.')">
        <template #cell-shop_name="{ row }">
            <Link :href="route('merchants.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.shop_name }}</Link>
            <p class="text-xs text-muted" dir="ltr">{{ row.email }}</p>
        </template>
        <template #cell-phone="{ row }">
            <span dir="ltr">{{ row.phone }}</span>
        </template>
        <template #cell-status="{ row }">
            <Badge :tone="row.status_tone" dot>{{ row.status_label }}</Badge>
        </template>
        <template #cell-tier="{ row }">
            <span v-if="row.tier">{{ row.tier }}</span>
            <span v-else class="text-muted">{{ t('Default') }}</span>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem :href="route('merchants.edit', row.id)"><Eye />{{ t('Open') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
