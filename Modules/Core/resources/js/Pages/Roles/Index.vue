<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, ShieldCheck, Trash2 } from '@/lib/icons';
import { computed, ref } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import DataTable from '@/Components/table/DataTable.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import RoleFormModal from './parts/RoleFormModal.vue';
import RolePermissionsModal from './parts/RolePermissionsModal.vue';
import RoleSummaryCell from './parts/RoleSummaryCell.vue';
import type { PermissionGroup, RoleRow } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ table: TablePayload<RoleRow>; permissionGroups: PermissionGroup[]; permissionOptions: Option[] }>();

const formOpen = ref(false);
const editing = ref<RoleRow | null>(null);
const viewing = ref<RoleRow | null>(null);
const viewOpen = computed({
    get: () => viewing.value !== null,
    set: (open: boolean) => {
        if (!open) viewing.value = null;
    },
});

const columns = computed<TableColumn[]>(() => [
    { key: 'label', label: t('Role Name'), sort: 'name' },
    { key: 'users_count', label: t('Users'), sort: 'users_count', class: 'tabular', toggleable: true },
    { key: 'summary', label: t('Permission Groups') },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'permission', label: t('Permissions'), options: props.permissionOptions },
    { type: 'boolean', key: 'has_users', label: t('Users'), trueLabel: t('Has users'), falseLabel: t('No users') },
    { type: 'date-range', key: 'created_at', label: t('Created At') },
]);

function openForm(role: RoleRow | null): void {
    editing.value = role;
    formOpen.value = true;
}

async function remove(row: RoleRow): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.label }), description: t('This cannot be undone.') })) {
        router.delete(route('roles.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Roles And Permissions')">
        <template #actions>
            <Button v-if="can('Roles And Permissions Create')" variant="primary" @click="openForm(null)">{{ t('Create Role') }}</Button>
        </template>
    </PageHeader>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No roles found')" :empty-text="t('Create a role, then give it to your staff.')">
        <template #cell-summary="{ row }">
            <RoleSummaryCell :summary="row.summary" />
        </template>
        <template #actions="{ row }">
            <Button variant="ghost" size="icon" :aria-label="t('View Permissions')" @click="viewing = row"><ShieldCheck class="size-4" /></Button>
            <Button v-if="can('Roles And Permissions Update') && row.editable" variant="ghost" size="icon" :aria-label="t('Edit')" @click="openForm(row)"><Pencil class="size-4" /></Button>
            <Button v-if="can('Roles And Permissions Delete') && !row.protected" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
    </DataTable>

    <RoleFormModal v-model:open="formOpen" :role="editing" :permission-groups="permissionGroups" />
    <RolePermissionsModal v-if="viewing" v-model:open="viewOpen" :role="viewing" />
</template>
