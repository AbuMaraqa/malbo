<script setup lang="ts">
import { ref } from 'vue';

import RowMenu from '@/Components/table/RowMenu.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import { can, t } from '@/lib/i18n';
import { Eye, Info, Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';

import RecordInfoModal from './RecordInfoModal.vue';
import RecordViewModal, { type ViewField } from './RecordViewModal.vue';

/**
 * The row actions every catalog table shares, each behind the permission core uses:
 * Info, View, Edit (a link, or the `edit` event for tables that edit in a modal), Delete.
 */
const props = defineProps<{
    /** The permission's name: `Category` in `Category Info`. */
    entity: string;
    /** The route names' prefix: `categories` in `categories.info`. */
    routes: string;
    id: number;
    title: string;
    fields: ViewField[];
    editHref?: string;
}>();

const emit = defineEmits<{ edit: []; delete: [] }>();

const infoOpen = ref(false);
const viewOpen = ref(false);

const infoUrl = (): string => route(`${props.routes}.info`, props.id);
</script>

<template>
    <RowMenu v-if="$slots.default || ['View', 'Info', 'Update', 'Delete'].some((action) => can(`${entity} ${action}`))">
        <!-- A table's own actions (invoice, barcode...) come first. -->
        <slot />
        <DropdownItem v-if="can(`${entity} View`)" @select="viewOpen = true"><Eye />{{ t('View') }}</DropdownItem>
        <DropdownItem v-if="can(`${entity} Info`)" @select="infoOpen = true"><Info />{{ t('Info') }}</DropdownItem>
        <template v-if="can(`${entity} Update`)">
            <DropdownItem v-if="editHref" :href="editHref"><Pencil />{{ t('Edit') }}</DropdownItem>
            <DropdownItem v-else @select="emit('edit')"><Pencil />{{ t('Edit') }}</DropdownItem>
        </template>
        <template v-if="can(`${entity} Delete`)">
            <DropdownSeparator />
            <DropdownItem destructive @select="emit('delete')"><Trash2 />{{ t('Delete') }}</DropdownItem>
        </template>
    </RowMenu>

    <!-- Outside the menu: they stay open after it closes. -->
    <RecordInfoModal v-if="can(`${entity} Info`)" v-model:open="infoOpen" :title="title" :url="infoUrl()" />
    <RecordViewModal v-if="can(`${entity} View`)" v-model:open="viewOpen" :title="title" :fields="fields" />
</template>
