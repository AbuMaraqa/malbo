<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { MessageDots, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    name: string;
    phone: string;
    email: string | null;
    locale: string | null;
    joined: string | null;
    joined_at: string | null;
    whatsapp_url: string;
}

defineProps<{ table: TablePayload<Row> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'phone', label: t('Phone') },
    { key: 'email', label: t('Email') },
    { key: 'locale', label: t('Language'), toggleable: true, hidden: true },
    { key: 'created_at', label: t('Joined'), sort: 'created_at' },
]);

const filters = computed<TableFilter[]>(() => [
    { key: 'locale', label: t('Language'), options: [{ value: 'ar', label: 'العربية' }, { value: 'en', label: 'English' }] },
    { type: 'boolean', key: 'has_email', label: t('Has an email'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'created_at', label: t('Joined') },
]);

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('members.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Members')" :description="t('Visitors who joined your members list from the home page. Message them first about new arrivals and offers.')" />

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No members yet')" :empty-text="t('Turn on the members section in Store appearance so visitors can join.')">
        <template #cell-phone="{ row }">
            <a :href="`tel:${row.phone}`" dir="ltr" class="hover:underline">{{ row.phone }}</a>
        </template>
        <template #cell-email="{ row }">
            <template v-if="row.email">{{ row.email }}</template>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-locale="{ row }">{{ row.locale === 'en' ? 'English' : 'العربية' }}</template>
        <template #cell-created_at="{ row }">
            <span class="whitespace-nowrap text-muted" :title="row.joined_at ?? undefined">{{ row.joined }}</span>
        </template>
        <template #actions="{ row }">
            <Button variant="ghost" size="icon" :href="row.whatsapp_url" external :aria-label="t('WhatsApp')"><MessageDots class="size-4" /></Button>
            <Button v-if="can('Member Delete')" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
    </DataTable>
</template>
