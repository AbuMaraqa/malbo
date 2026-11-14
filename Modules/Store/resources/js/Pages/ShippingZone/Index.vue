<script setup lang="ts">
import RowMenu from '@/Components/table/RowMenu.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Toggle from '@/Components/form/Toggle.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import ShippingSettingsCard from './ShippingSettingsCard.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    fee: string;
    eta: string;
    free_shipping_threshold: string | null;
    cities_count: number;
    sort_order: number;
    active: boolean;
}

defineProps<{ table: TablePayload<Row>; settings: { default_free_shipping_threshold: number | null } }>();

const page = usePage();
/** A zone that could not be switched on: one of its cities is in another active zone. */
const statusError = computed(() => (page.props.errors as Record<string, string>).active ?? null);

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'fee', label: t('Delivery fee'), sort: 'fee', class: 'tabular' },
    { key: 'eta', label: t('Delivery time'), sort: 'eta_max_days' },
    { key: 'free_shipping_threshold', label: t('Free delivery from'), sort: 'free_shipping_threshold', class: 'tabular', toggleable: true },
    { key: 'cities_count', label: t('Cities'), sort: 'cities_count', class: 'tabular' },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular', toggleable: true, hidden: true },
    { key: 'active', label: t('Active'), sort: 'is_active' },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'text', key: 'name', label: t('Name'), placeholder: t('Search by name') },
    { type: 'boolean', key: 'is_active', label: t('Active'), trueLabel: t('Active'), falseLabel: t('Inactive') },
    { type: 'number-range', key: 'fee', label: t('Delivery fee') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

function setActive(row: Row, active: boolean): void {
    router.put(route('shipping-zones.status', row.id), { active }, { preserveScroll: true });
}

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('shipping-zones.destroy', row.id), { preserveScroll: true });
    }
}

async function removeMany(ids: (number | string)[]): Promise<void> {
    if (await confirm({ title: t('Delete :count items?', { count: ids.length }), description: t('This cannot be undone.') })) {
        router.delete(route('shipping-zones.destroy-many'), { data: { ids }, preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Shipping zones')">
        <template #actions>
            <Button v-if="can('Shipping Zone Create')" variant="primary" :href="route('shipping-zones.add')">{{ t('Add shipping zone') }}</Button>
        </template>
    </PageHeader>

    <p v-if="statusError" role="alert" class="mb-4 rounded-control border border-danger px-4 py-3 text-sm text-danger">{{ statusError }}</p>

    <ShippingSettingsCard v-if="can('Shipping Zone Update')" :threshold="settings.default_free_shipping_threshold" class="mb-4" />

    <DataTable
        :table="table"
        :columns="columns"
        :filters="filters"
        :selectable="can('Shipping Zone Delete')"
        :empty-title="t('No shipping zones found')"
        :empty-text="t('Cities in no active zone get no delivery. Add a zone to start delivering.')"
    >
        <template #cell-name="{ row }">
            <Link v-if="can('Shipping Zone Update')" :href="route('shipping-zones.edit', row.id)" class="font-medium text-brand-600 hover:underline dark:text-brand-400">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-free_shipping_threshold="{ row }">
            <span v-if="row.free_shipping_threshold">{{ row.free_shipping_threshold }}</span>
            <span v-else class="text-muted">{{ t('Store default') }}</span>
        </template>
        <template #cell-active="{ row }">
            <Toggle v-if="can('Shipping Zone Update')" :model-value="row.active" :aria-label="t('Active')" @update:model-value="setActive(row, !!$event)" />
            <Badge v-else :tone="row.active ? 'success' : 'neutral'">{{ row.active ? t('Active') : t('Inactive') }}</Badge>
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Shipping Zone Update')" :href="route('shipping-zones.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownSeparator v-if="can('Shipping Zone Delete')" />
                <DropdownItem v-if="can('Shipping Zone Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
        <template #bulk="{ selected }">
            <Button variant="danger" size="sm" @click="removeMany(selected)">{{ t('Delete') }}</Button>
        </template>
    </DataTable>
</template>
