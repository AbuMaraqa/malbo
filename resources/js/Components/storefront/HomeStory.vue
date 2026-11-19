<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';

import BrandMark from './BrandMark.vue';
import BrandSparkle from './BrandSparkle.vue';

/**
 * The store's story as a full-width maroon band with the MALBO pattern, in three parts: its picture
 * in an arch, the text in the middle, and a cream arch holding the maroon mark (the brand's arched
 * forms, as in its shop interiors). Without a picture the text and the cream arch share the band.
 */
defineProps<{ story: { title: string | null; text: string; image: string | null; about: string | null } }>();
</script>

<template>
    <section id="story" data-section="story" class="reveal mt-24 scroll-mt-36">
        <div class="relative grid min-h-[26rem] items-stretch gap-8 bg-(--night) bg-malbo-pattern px-4 py-12 text-white sm:px-8 lg:grid-cols-[1fr_1.15fr_1fr] lg:px-12">
            <div v-if="story.image" class="relative h-80 overflow-hidden rounded-t-[12rem] rounded-b-[1.75rem] lg:h-auto lg:min-h-[22rem]">
                <img :src="story.image" alt="" loading="lazy" class="absolute inset-0 size-full object-cover" />
            </div>
            <div class="flex flex-col items-center justify-center py-2 text-center" :class="{ 'lg:col-span-2': !story.image }">
                <p class="inline-flex items-center gap-2 text-[0.9375rem] font-semibold text-(--gold) ltr:text-[0.8125rem] ltr:tracking-[0.16em] ltr:uppercase">
                    <BrandSparkle class="h-4 w-3" />
                    {{ t('Our story') }}
                </p>
                <h2 v-if="story.title" class="mt-3 text-3xl leading-snug font-bold sm:text-4xl">{{ story.title }}</h2>
                <p class="mt-4 line-clamp-4 max-w-md text-[0.9375rem] leading-7 whitespace-pre-line text-white/80">{{ story.text }}</p>
                <Link
                    v-if="story.about"
                    :href="story.about"
                    class="mt-8 inline-flex min-h-11 items-center rounded-full bg-(--shell) px-8 text-[0.875rem] font-semibold text-(--night) transition-colors hover:bg-(--gold) ltr:text-[0.8125rem] ltr:tracking-[0.1em] ltr:uppercase"
                >
                    {{ t('Read our story') }}
                </Link>
            </div>
            <!-- The cream arch with the maroon mark: the approved version for light surfaces. -->
            <div class="hidden place-items-center rounded-t-[12rem] rounded-b-[1.75rem] bg-(--paper) px-10 lg:grid">
                <BrandMark class="h-44" />
            </div>
        </div>
    </section>
</template>
