<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import DataTable from '@/Components/table/DataTable.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { CheckRead, EnvelopeUnread, Eye, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

import MessageModal, { type ContactMessageRow } from './parts/MessageModal.vue';

defineOptions({ layout: AdminLayout });

defineProps<{ table: TablePayload<ContactMessageRow> }>();

const columns = computed<TableColumn[]>(() => [
    { key: 'is_read', label: t('Status') },
    { key: 'name', label: t('Name'), sort: 'name' },
    { key: 'phone', label: t('Phone') },
    { key: 'email', label: t('Email'), toggleable: true },
    { key: 'subject', label: t('Topic'), toggleable: true },
    { key: 'message', label: t('Message') },
    { key: 'created_at', label: t('Received'), sort: 'created_at' },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'read', label: t('Status'), trueLabel: t('Opened'), falseLabel: t('Unread') },
    { type: 'text', key: 'subject', label: t('Topic') },
    { key: 'locale', label: t('Language'), options: [{ value: 'ar', label: 'العربية' }, { value: 'en', label: 'English' }] },
    { type: 'boolean', key: 'has_phone', label: t('Has a phone number'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'has_email', label: t('Has an email'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'created_at', label: t('Received') },
]);

const openRow = ref<ContactMessageRow | null>(null);
const modalOpen = ref(false);

const visitOptions = { preserveScroll: true, only: ['table', 'flash'] };

function open(row: ContactMessageRow): void {
    openRow.value = row;
    modalOpen.value = true;

    if (!row.is_read) {
        router.post(route('contact-messages.open', row.id), {}, visitOptions);
    }
}

function unread(row: ContactMessageRow): void {
    router.post(route('contact-messages.unread', row.id), {}, visitOptions);
}

function markRead(ids: (number | string)[]): void {
    router.post(route('contact-messages.read-many'), { ids }, visitOptions);
}

async function remove(row: ContactMessageRow): Promise<void> {
    if (await confirm({ title: t('Delete the message from :name?', { name: row.name }), description: t('This cannot be undone.') })) {
        router.delete(route('contact-messages.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Customer messages')" :description="t('Messages sent from the contact page of your store. Reply by phone, WhatsApp or email.')" />

    <DataTable
        :table="table"
        :columns="columns"
        :filters="filters"
        :selectable="can('Contact Message Update')"
        :empty-title="t('No messages yet')"
        :empty-text="t('Messages sent from the contact page of your store will appear here.')"
    >
        <template #cell-is_read="{ row }">
            <Badge :tone="row.is_read ? 'neutral' : 'success'">{{ row.is_read ? t('Opened') : t('Unread') }}</Badge>
        </template>
        <template #cell-name="{ row }">
            <span :class="{ 'font-bold': !row.is_read }">{{ row.name }}</span>
        </template>
        <template #cell-phone="{ row }">
            <a v-if="row.phone" :href="`tel:${row.phone}`" dir="ltr" class="hover:underline">{{ row.phone }}</a>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-email="{ row }">
            <a v-if="row.email" :href="`mailto:${row.email}`" class="hover:underline">{{ row.email }}</a>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-subject="{ row }">
            <template v-if="row.subject">{{ row.subject }}</template>
            <span v-else class="text-muted">—</span>
        </template>
        <template #cell-message="{ row }">
            <span class="line-clamp-2 max-w-md" :class="{ 'font-bold': !row.is_read }">{{ row.message }}</span>
        </template>
        <template #cell-created_at="{ row }">
            <span class="whitespace-nowrap text-muted" :title="row.received_at ?? undefined">{{ row.received }}</span>
        </template>
        <template #actions="{ row }">
            <Button v-if="can('Contact Message View')" variant="ghost" size="icon" :aria-label="t('View')" @click="open(row)"><Eye class="size-4" /></Button>
            <Button v-if="row.is_read && can('Contact Message Update')" variant="ghost" size="icon" :aria-label="t('Mark as unread')" @click="unread(row)"><EnvelopeUnread class="size-4" /></Button>
            <Button v-if="can('Contact Message Delete')" variant="ghost" size="icon" :aria-label="t('Delete')" @click="remove(row)"><Trash2 class="size-4" /></Button>
        </template>
        <template #bulk="{ selected }">
            <Button size="sm" @click="markRead(selected)"><CheckRead class="size-4" />{{ t('Mark as read') }}</Button>
        </template>
    </DataTable>

    <MessageModal v-if="openRow" v-model:open="modalOpen" :message="openRow" />
</template>
