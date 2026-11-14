<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PageBreadcrumb from './PageBreadcrumb.vue';

const props = defineProps<{
    title: string;
    description?: string;
    /** How many records the page lists, shown after the title: "Products (24)". */
    count?: number;
    /** Parents of this page: the current page is `title`, home is added automatically. */
    parents?: { title: string; href: string }[];
}>();

const crumbs = computed(() => [{ title: t('Home'), href: route('dashboard') }, ...(props.parents ?? [])]);
</script>

<template>
    <Head :title="title" />

    <!-- On a wide screen the breadcrumb sits in the header bar; on a small one, above the title. -->
    <Teleport defer to="#header-breadcrumbs">
        <PageBreadcrumb :crumbs="crumbs" :current="title" />
    </Teleport>

    <!-- The page toolbar: title and description, then the page's actions on the far edge. -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-x-4 gap-y-3 pt-1">
        <div class="min-w-0">
            <PageBreadcrumb :crumbs="crumbs" :current="title" class="mb-1.5 lg:hidden" />
            <h1 class="text-xl leading-8 font-semibold text-gray-900 dark:text-white">
                {{ title }}<span v-if="count !== undefined" class="tabular ms-1 font-normal text-muted">({{ count.toLocaleString('en') }})</span>
            </h1>
            <p v-if="description" class="mt-0.5 max-w-3xl text-sm text-muted">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2.5"><slot name="actions" /></div>
    </div>
</template>
