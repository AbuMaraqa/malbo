<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import ArrowMark from './ArrowMark.vue';
import { eyebrow, glassStrong, linkArrow, sectionTitle } from './styles';

defineProps<{ story: { title: string | null; text: string; image: string | null; about: string | null } }>();
</script>

<template>
    <section id="story" data-section="story" class="relative mt-28 overflow-hidden py-16 sm:py-24">
        <div aria-hidden="true" class="blob absolute end-[8%] top-10 size-72 rounded-full bg-(--gold) opacity-50 sm:size-96"></div>
        <div class="relative mx-auto grid max-w-310 items-center px-4" :class="{ 'lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]': story.image }">
            <div
                class="reveal relative z-10 rounded-[2rem] p-7 sm:p-12"
                :class="[glassStrong, story.image ? 'order-2 -mt-20 sm:mx-10 lg:order-1 lg:mx-0 lg:-me-24 lg:mt-0' : 'mx-auto max-w-3xl text-center']"
            >
                <p :class="eyebrow">{{ t('Our story') }}</p>
                <span aria-hidden="true" class="mt-3 block font-serif text-7xl leading-none text-store/30">“</span>
                <h2 v-if="story.title" class="-mt-6 leading-snug" :class="sectionTitle">{{ story.title }}</h2>
                <p class="mt-5 text-[17px] leading-9 whitespace-pre-line text-(--ink-soft)">{{ story.text }}</p>
                <Link v-if="story.about" :href="story.about" class="mt-7" :class="linkArrow">{{ t('Read our story') }} <ArrowMark /></Link>
            </div>
            <img v-if="story.image" :src="story.image" alt="" loading="lazy" class="reveal order-1 aspect-4/3 w-full rounded-[2rem] object-cover lg:order-2 lg:aspect-5/4" />
        </div>
    </section>
</template>
