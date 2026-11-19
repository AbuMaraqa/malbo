<script setup lang="ts">
import { t } from '@/lib/i18n';

import BrandPetal from './BrandPetal.vue';
import StoreBreadcrumb from './StoreBreadcrumb.vue';
import { container } from './styles';

/**
 * The banner at the top of an inner page: its title and the way back home, centered in white. It
 * is maroon with the MALBO pattern and a large faint petal; with an image (a category's), the
 * picture fills the banner under a maroon shade.
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
    <section data-section="page-hero" class="relative overflow-hidden bg-(--night) text-white" :class="{ 'bg-malbo-pattern': !image }">
        <template v-if="image">
            <img :src="image" alt="" class="absolute inset-0 size-full object-cover" />
            <div aria-hidden="true" class="absolute inset-0 bg-(--night)/70"></div>
        </template>
        <BrandPetal v-else class="absolute -end-24 top-1/2 w-[34rem] -translate-y-1/2 text-white opacity-[0.07] max-sm:hidden" />
        <div :class="container" class="relative py-16 text-center sm:py-24">
            <h1 class="mx-auto max-w-3xl text-3xl leading-tight font-bold text-balance ltr:uppercase sm:text-5xl">{{ title }}</h1>
            <p v-if="lead" class="mx-auto mt-4 max-w-2xl text-lg leading-8 whitespace-pre-line text-white/80">{{ lead }}</p>
            <p v-if="updated" class="mt-3 text-sm text-white/70">{{ t('Last updated :date', { date: updated }) }}</p>
            <StoreBreadcrumb :title="title" :parents="parents" on-dark class="mt-5" />
        </div>
    </section>
</template>
