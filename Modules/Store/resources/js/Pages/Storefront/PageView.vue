<script setup lang="ts">
import FaqList from '@/Components/storefront/FaqList.vue';
import HelpMenu from '@/Components/storefront/HelpMenu.vue';
import PageHero from '@/Components/storefront/PageHero.vue';
import PageOutlineMenu from '@/Components/storefront/PageOutlineMenu.vue';
import { prose, proseSections } from '@/Components/storefront/styles';
import type { PageLink } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    page: { title: string; lead: string | null };
    updated: string | null;
    body: string | null;
    toc: { id: string; title: string }[];
    faq: { intro: string; items: { question: string; answer: string }[] } | null;
    help: { about: PageLink[]; policies: PageLink[] };
    whatsapp: string | null;
}>();
</script>

<template>
    <div>
        <PageHero :title="page.title" :lead="page.lead" :updated="updated" />

        <div class="mx-auto grid max-w-310 gap-10 px-4 pt-10 lg:grid-cols-[minmax(0,1fr)_18rem] lg:gap-14 lg:pt-14">
            <div class="min-w-0">
                <PageOutlineMenu v-if="toc.length" :toc="toc" variant="fold" />
                <FaqList v-if="faq" :faq="faq" />
                <!-- Sanitized on the server (RichText). -->
                <article v-else-if="body" class="max-w-3xl" :class="[prose, proseSections]" v-html="body"></article>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-28 lg:self-start">
                <PageOutlineMenu v-if="toc.length" :toc="toc" variant="card" />
                <HelpMenu :help="help" :whatsapp="whatsapp" />
            </aside>
        </div>
    </div>
</template>
