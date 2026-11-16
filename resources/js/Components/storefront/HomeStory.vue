<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import { btnDark, container, eyebrow, sectionTitle } from './styles';

defineProps<{ story: { title: string | null; text: string; image: string | null; about: string | null } }>();
</script>

<template>
    <section id="story" data-section="story" :class="container" class="reveal mt-24 scroll-mt-36">
        <div class="grid items-center gap-10" :class="{ 'lg:grid-cols-2 lg:gap-16': story.image }">
            <div v-if="story.image" class="relative">
                <span aria-hidden="true" class="absolute -start-4 -top-4 hidden size-full rounded-xl border-2 border-(--gold) sm:block"></span>
                <img :src="story.image" alt="" loading="lazy" class="relative aspect-4/3 w-full rounded-xl object-cover" />
            </div>
            <div :class="{ 'mx-auto max-w-3xl text-center': !story.image }">
                <p :class="eyebrow">{{ t('Our story') }}</p>
                <h2 v-if="story.title" class="mt-3 leading-snug" :class="sectionTitle">{{ story.title }}</h2>
                <p class="mt-5 text-[17px] leading-9 whitespace-pre-line text-(--ink-soft)">{{ story.text }}</p>
                <Link v-if="story.about" :href="story.about" class="mt-8" :class="btnDark">{{ t('Read our story') }} <ArrowMark /></Link>
            </div>
        </div>
    </section>
</template>
