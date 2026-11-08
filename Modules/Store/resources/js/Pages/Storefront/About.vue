<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import StoreBreadcrumb from '@/Components/storefront/StoreBreadcrumb.vue';
import { btnBrand, btnOutline, prose, proseSections, sectionTitle } from '@/Components/storefront/styles';
import type { BrandValueText } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    page: { title: string; lead: string | null };
    storeName: string;
    image: string | null;
    body: string | null;
    values: BrandValueText[];
    whatsapp: string | null;
}>();

const number = (index: number): string => String(index + 1).padStart(2, '0');
</script>

<template>
    <div>
        <section data-section="about-hero" class="relative overflow-hidden border-b border-line bg-(--sand-light)">
            <div aria-hidden="true" class="absolute end-[4%] -top-24 size-72 rounded-full bg-store opacity-[0.12] sm:size-96"></div>
            <div aria-hidden="true" class="absolute start-[40%] bottom-10 size-32 rounded-full bg-(--gold) opacity-40"></div>
            <div class="relative mx-auto grid max-w-310 items-center gap-10 px-4 pt-10 pb-14 sm:pt-14 sm:pb-20 lg:grid-cols-2">
                <div>
                    <StoreBreadcrumb :title="page.title" />
                    <p class="mt-6 text-sm font-bold tracking-wide text-store-700">{{ storeName }}</p>
                    <h1 class="mt-2 text-4xl leading-tight font-bold text-balance sm:text-5xl lg:text-[56px]">{{ page.title }}</h1>
                    <p v-if="page.lead" class="mt-5 max-w-xl text-lg leading-8 text-(--ink-soft)">{{ page.lead }}</p>
                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link :href="route('store.shop')" :class="btnBrand">{{ t('Shop now') }}</Link>
                        <Link :href="route('store.contact')" :class="btnOutline">{{ t('Contact us') }}</Link>
                    </div>
                </div>
                <img v-if="image" :src="image" alt="" fetchpriority="high" class="aspect-4/3 w-full rounded-lg object-cover shadow-xl shadow-black/5" />
            </div>
        </section>

        <section v-if="body" data-section="about-text" class="mx-auto max-w-310 px-4 pt-14 sm:pt-20">
            <!-- Sanitized on the server (RichText). -->
            <article class="mx-auto max-w-3xl" :class="[prose, proseSections]" v-html="body"></article>
        </section>

        <section v-if="values.length" data-section="about-values" class="mx-auto mt-20 max-w-310 px-4">
            <h2 class="text-center" :class="sectionTitle">{{ t('What we believe in') }}</h2>
            <ul class="mt-8 grid gap-5 md:grid-cols-3">
                <li v-for="(value, index) in values" :key="index" class="rounded-lg border border-line bg-white p-7">
                    <span class="block text-[28px] font-bold text-store-700">{{ number(index) }}</span>
                    <h3 class="mt-3 mb-2 text-[19px] font-bold">{{ value.title }}</h3>
                    <p v-if="value.text" class="text-[15px] leading-7 text-(--ink-soft)">{{ value.text }}</p>
                </li>
            </ul>
        </section>

        <section data-section="about-cta" class="mx-auto mt-20 max-w-310 px-4">
            <div class="relative overflow-hidden rounded-lg bg-(--sand) px-6 py-12 sm:px-12">
                <div aria-hidden="true" class="absolute -end-10 -bottom-28 size-80 rounded-full bg-store opacity-20"></div>
                <div class="relative flex flex-wrap items-center justify-between gap-6">
                    <div class="max-w-xl">
                        <h2 :class="sectionTitle">{{ t('Ready to find something you love?') }}</h2>
                        <p class="mt-2 text-[17px] leading-8 text-(--ink-soft)">{{ t('Browse our products, or ask us anything first.') }}</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <Link :href="route('store.shop')" :class="btnBrand">{{ t('Shop now') }}</Link>
                        <a v-if="whatsapp" :href="`https://wa.me/${whatsapp}`" target="_blank" rel="noopener" class="bg-white/60" :class="btnOutline">{{ t('Chat on WhatsApp') }}</a>
                        <Link v-else :href="route('store.contact')" class="bg-white/60" :class="btnOutline">{{ t('Contact us') }}</Link>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
