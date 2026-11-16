<script setup lang="ts">
import { t } from '@/lib/i18n';

import BrandPetal from './BrandPetal.vue';
import BrandSparkle from './BrandSparkle.vue';
import StoreBreadcrumb from './StoreBreadcrumb.vue';
import { container } from './styles';

/**
 * The band at the top of an inner page: its title, a line under it and the way back home. Without
 * an image it is MALBO cream with the soft pattern and two maroon petals; with one (a category's),
 * the picture fills the band under a maroon shade.
 */
defineProps<{
    title: string;
    lead?: string | null;
    updated?: string | null;
    parents?: { name: string; url: string }[];
    image?: string | null;
}>();
</script>

<template>
    <section data-section="page-hero" class="relative overflow-hidden" :class="image ? 'bg-(--night) text-white' : 'bg-(--sand) bg-brand-pattern-soft'">
        <template v-if="image">
            <img :src="image" alt="" class="absolute inset-0 size-full object-cover" />
            <div aria-hidden="true" class="absolute inset-0 bg-(--night)/65"></div>
        </template>
        <template v-else>
            <BrandPetal class="absolute -end-16 -top-6 w-72 text-store opacity-90 max-sm:w-44 max-sm:opacity-25" />
            <BrandPetal class="absolute -start-20 -bottom-8 w-64 -scale-100 text-store opacity-15 max-sm:hidden" />
        </template>
        <div :class="container" class="relative py-14 text-center sm:py-20">
            <BrandSparkle class="mx-auto mb-4 h-6 w-4.5 text-(--gold)" />
            <h1 class="mx-auto max-w-3xl text-3xl leading-tight font-bold text-balance sm:text-[2.75rem]">{{ title }}</h1>
            <p v-if="lead" class="mx-auto mt-4 max-w-2xl text-lg leading-8 whitespace-pre-line" :class="image ? 'text-white/85' : 'text-(--ink-soft)'">{{ lead }}</p>
            <p v-if="updated" class="mt-3 text-sm" :class="image ? 'text-white/75' : 'text-muted'">{{ t('Last updated :date', { date: updated }) }}</p>
            <StoreBreadcrumb :title="title" :parents="parents" class="mt-6 inline-flex rounded-md bg-white px-4 py-2 shadow-sm" />
        </div>
    </section>
</template>
