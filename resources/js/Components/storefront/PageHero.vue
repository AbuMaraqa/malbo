<script setup lang="ts">
import { t } from '@/lib/i18n';

import StoreBreadcrumb from './StoreBreadcrumb.vue';

/** The header band of an information page. */
defineProps<{ title: string; lead?: string | null; updated?: string | null }>();
</script>

<template>
    <section data-section="page-hero" class="relative overflow-hidden border-b border-line bg-(--sand-light)">
        <div aria-hidden="true" class="absolute -end-24 -top-24 size-64 rounded-full bg-store opacity-[0.12] sm:end-[6%] sm:size-80"></div>
        <div aria-hidden="true" class="absolute end-[24%] top-20 size-36 rounded-full bg-(--gold) opacity-40 max-sm:hidden"></div>
        <div class="relative mx-auto max-w-310 px-4 pt-10 pb-12 sm:pt-14 sm:pb-16">
            <StoreBreadcrumb :title="title" />
            <h1 class="mt-6 max-w-3xl text-4xl leading-tight font-bold text-balance sm:text-5xl">{{ title }}</h1>
            <p v-if="lead" class="mt-4 max-w-2xl text-lg leading-8 text-(--ink-soft)">{{ lead }}</p>
            <p v-if="updated" class="mt-4 text-sm text-muted">{{ t('Last updated :date', { date: updated }) }}</p>
        </div>
    </section>
</template>
