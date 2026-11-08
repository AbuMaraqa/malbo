<script setup lang="ts">
import { Eye, Info, Pencil, Trash2 } from '@/lib/icons';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { can, t } from '@/lib/i18n';
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

defineEmits<{ edit: []; delete: [] }>();

const infoOpen = ref(false);
const viewOpen = ref(false);

const infoUrl = (): string => route(`${props.routes}.info`, props.id);
</script>

<template>
    <Button v-if="can(`${entity} Info`)" variant="ghost" size="icon" :aria-label="t('Info')" @click="infoOpen = true"><Info class="size-4" /></Button>
    <Button v-if="can(`${entity} View`)" variant="ghost" size="icon" :aria-label="t('View')" @click="viewOpen = true"><Eye class="size-4" /></Button>
    <template v-if="can(`${entity} Update`)">
        <Button v-if="editHref" variant="ghost" size="icon" :href="editHref" :aria-label="t('Edit')"><Pencil class="size-4" /></Button>
        <Button v-else variant="ghost" size="icon" :aria-label="t('Edit')" @click="$emit('edit')"><Pencil class="size-4" /></Button>
    </template>
    <Button v-if="can(`${entity} Delete`)" variant="ghost" size="icon" :aria-label="t('Delete')" @click="$emit('delete')"><Trash2 class="size-4" /></Button>

    <RecordInfoModal v-if="can(`${entity} Info`)" v-model:open="infoOpen" :title="title" :url="infoUrl()" />
    <RecordViewModal v-if="can(`${entity} View`)" v-model:open="viewOpen" :title="title" :fields="fields" />
</template>
