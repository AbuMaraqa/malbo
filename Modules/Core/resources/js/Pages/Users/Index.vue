<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Eye, Pencil, Trash2 } from '@/lib/icons';
import { computed, ref } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import UserViewModal from './parts/UserViewModal.vue';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    roles: { name: string; label: string }[];
    branch: string | null;
    created_at: string | null;
    updated_at: string | null;
    can_manage: boolean;
}

const props = defineProps<{ table: TablePayload<Row>; roleOptions: Option[]; branchOptions: Option[] }>();

const viewing = ref<Row | null>(null);
const viewOpen = computed({
    get: () => viewing.value !== null,
    set: (open: boolean) => {
        if (!open) viewing.value = null;
    },
});

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'email', label: t('Email'), sort: 'email' },
    { key: 'phone', label: t('Phone'), toggleable: true },
    { key: 'roles', label: t('Roles') },
    { key: 'branch', label: t('Branches'), toggleable: true },
    { key: 'created_at', label: t('Created At'), sort: 'created_at', toggleable: true },
    { key: 'updated_at', label: t('Updated At'), sort: 'updated_at', toggleable: true, hidden: true },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'role', label: t('Roles'), options: props.roleOptions },
    { key: 'branch', label: t('Branches'), options: props.branchOptions },
    { type: 'text', key: 'name', label: t('Name') },
    { type: 'text', key: 'email', label: t('Email') },
    { type: 'text', key: 'phone', label: t('Phone') },
    { type: 'date-range', key: 'created_at', label: t('Registration Date') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('users.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Users List')">
        <template #actions>
            <Button v-if="can('User Create')" variant="primary" :href="route('users.add')">{{ t('Add User') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No users found')" :empty-text="t('Add the staff who work in your store.')">
        <template #cell-name="{ row }">
            <Link v-if="can('User Update') && row.can_manage" :href="route('users.edit', row.id)" class="font-medium text-brand-600 dark:text-brand-400 hover:underline dark:text-brand-400">{{ row.name }}</Link>
            <template v-else>{{ row.name }}</template>
        </template>
        <template #cell-email="{ row }">
            <span dir="ltr">{{ row.email }}</span>
        </template>
        <template #cell-phone="{ row }">
            <span dir="ltr">{{ row.phone }}</span>
        </template>
        <template #cell-roles="{ row }">
            <span class="flex flex-wrap gap-1">
                <Badge v-for="role in row.roles" :key="role.name" :tone="role.name === 'Super Admin' ? 'danger' : 'brand'">{{ role.label }}</Badge>
            </span>
        </template>
        <template #actions="{ row }">
            <Button v-if="can('User View')" variant="ghost" size="icon" :aria-label="t('View')" @click="viewing = row"><Eye class="size-4" /></Button>
            <Button v-if="can('User Update') && row.can_manage" variant="ghost" size="icon" :href="route('users.edit', row.id)" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
            <Button v-if="can('User Delete') && row.can_manage" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
    </DataTable>

    <UserViewModal v-if="viewing" v-model:open="viewOpen" :user="viewing" />
</template>
