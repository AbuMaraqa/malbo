<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import DataTable from '@/Components/table/DataTable.vue';
import RowMenu from '@/Components/table/RowMenu.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import DropdownItem from '@/Components/ui/DropdownItem.vue';
import DropdownSeparator from '@/Components/ui/DropdownSeparator.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { confirm } from '@/lib/confirm';
import { can, t } from '@/lib/i18n';
import { ExternalLink, Pencil, Trash2 } from '@/lib/icons';
import { route } from '@/lib/route';
import type { TableColumn, TableFilter, TablePayload } from '@/types/table';

defineOptions({ layout: AdminLayout });

interface Row {
    id: number;
    image: string | null;
    permalink: string;
    caption: string | null;
    products_count: number;
    sort_order: number;
    is_active: boolean;
    source: string;
    posted_at: string | null;
}

const props = defineProps<{ table: TablePayload<Row>; settings: { enabled: boolean; title: string; profile_url: string | null } }>();

const settingsForm = useForm({ enabled: props.settings.enabled, title: props.settings.title });

const columns = computed<TableColumn[]>(() => [
    { key: 'post', label: t('Post') },
    { key: 'products_count', label: t('Products'), class: 'tabular' },
    { key: 'sort_order', label: t('Sort Order'), sort: 'sort_order', class: 'tabular' },
    { key: 'posted_at', label: t('Posted on'), sort: 'posted_at' },
    { key: 'is_active', label: t('Shown in the store') },
]);

const filters = computed<TableFilter[]>(() => [
    { type: 'boolean', key: 'is_active', label: t('Shown in the store'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'boolean', key: 'has_products', label: t('With products'), trueLabel: t('Yes'), falseLabel: t('No') },
    { type: 'date-range', key: 'posted_at', label: t('Posted on') },
    { type: 'date-range', key: 'created_at', label: t('Created at') },
]);

function toggle(row: Row, value: boolean | undefined): void {
    router.put(route('instagram-posts.status', row.id), { active: !!value }, { preserveScroll: true, only: ['table', 'flash'] });
}

async function remove(row: Row): Promise<void> {
    if (await confirm({ title: t('Delete this post?'), description: t('It leaves the store; nothing changes on Instagram.') })) {
        router.delete(route('instagram-posts.destroy', row.id), { preserveScroll: true });
    }
}
</script>

<template>
    <PageHeader :title="t('Instagram')" :description="t('Posts shown in the store, each with the products it shows. Enter them here; a connection to Instagram can bring them in later.')">
        <template #actions>
            <Button v-if="can('Instagram Post Create')" variant="primary" :href="route('instagram-posts.add')">{{ t('Add Instagram post') }}</Button>
        </template>
    </PageHeader>

    <Card v-if="can('Instagram Post Update')" :title="t('Home page section')" class="mb-4">
        <form class="grid items-end gap-4 md:grid-cols-3" @submit.prevent="settingsForm.put(route('instagram-posts.settings'), { preserveScroll: true })">
            <Toggle v-model="settingsForm.enabled" :label="t('Show the posts on the home page')" />
            <TextInput v-model="settingsForm.title" :label="t('Section title')" :maxlength="120" :hint="t('In the language you are using. Left empty, the store uses its own title.')" :error="settingsForm.errors.title" />
            <div class="flex items-center gap-3">
                <Button type="submit" variant="primary" :loading="settingsForm.processing">{{ t('Save') }}</Button>
                <span v-if="!settings.profile_url" class="text-sm text-muted">{{ t('Add the Instagram link in the store appearance to show a follow button.') }}</span>
            </div>
        </form>
    </Card>

    <DataTable :table="table" :columns="columns" :filters="filters" :empty-title="t('No Instagram posts yet')" :empty-text="t('Add one, or change the filters.')">
        <template #cell-post="{ row }">
            <div class="flex items-center gap-3">
                <img v-if="row.image" :src="row.image" alt="" class="size-12 rounded-lg object-cover" />
                <div class="min-w-0">
                    <a :href="row.permalink" target="_blank" rel="noopener" dir="ltr" class="block truncate text-sm text-brand-600 hover:underline dark:text-brand-400">{{ row.permalink }}</a>
                    <p v-if="row.caption" class="line-clamp-1 text-sm text-muted">{{ row.caption }}</p>
                </div>
            </div>
        </template>
        <template #cell-is_active="{ row }">
            <Toggle :model-value="row.is_active" :disabled="!can('Instagram Post Update')" :aria-label="t('Shown in the store')" @update:model-value="toggle(row, $event)" />
        </template>
        <template #actions="{ row }">
            <RowMenu>
                <DropdownItem v-if="can('Instagram Post Update')" :href="route('instagram-posts.edit', row.id)"><Pencil />{{ t('Edit') }}</DropdownItem>
                <DropdownItem :href="row.permalink" external><ExternalLink />{{ t('Open on Instagram') }}</DropdownItem>
                <DropdownSeparator v-if="can('Instagram Post Delete')" />
                <DropdownItem v-if="can('Instagram Post Delete')" destructive @select="remove(row)"><Trash2 />{{ t('Delete') }}</DropdownItem>
            </RowMenu>
        </template>
    </DataTable>
</template>
