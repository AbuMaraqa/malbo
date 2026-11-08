<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { ExternalLink } from '@/lib/icons';
import { route } from '@/lib/route';

import PageFields, { type PageForm } from './PageFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{ page: PageForm & { id: number }; isSystem: boolean; viewUrl: string }>();

const { id: _id, ...values } = props.page;
const form = useForm<PageForm>(values);
</script>

<template>
    <PageHeader :title="t('Edit page')" :parents="[{ title: t('Store pages'), href: route('pages.index') }]">
        <template #actions>
            <a :href="viewUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm text-brand-600 hover:underline dark:text-brand-400">
                <ExternalLink class="size-4" />
                {{ t('View page') }}
            </a>
        </template>
    </PageHeader>
    <form @submit.prevent="form.put(route('pages.update', page.id))">
        <PageFields :form="form" :is-system="isSystem" />
        <FormActions :processing="form.processing" :cancel-href="route('pages.index')" />
    </form>
</template>
