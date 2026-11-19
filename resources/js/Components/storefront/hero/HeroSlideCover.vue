<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import ArrowMark from '../ArrowMark.vue';
import CampaignLink from '../campaign/CampaignLink.vue';
import type { HeroSlideCard } from '../types';

/**
 * A slide set up in the admin: its picture across the whole banner (the phones' own picture on
 * small screens when there is one), slowly zooming while it shows. Its texts, when it has any,
 * rise in one after another over a shade on the reading side; a slide without texts is a designed
 * banner, and the whole picture is its link.
 */
const props = defineProps<{
    slide: HeroSlideCard;
    /** The first slide: its title is the page's h1 and its picture loads first. */
    first: boolean;
}>();

const hasText = computed(() => !!(props.slide.eyebrow || props.slide.title || props.slide.subtitle));
const Heading = computed(() => (props.first ? 'h1' : 'h2'));
</script>

<template>
    <div class="absolute inset-0 overflow-hidden">
        <picture class="hero-zoom absolute inset-0 block">
            <source v-if="slide.mobile_image" media="(max-width: 639px)" :srcset="slide.mobile_image" />
            <img
                :src="slide.image"
                alt=""
                :fetchpriority="first ? 'high' : undefined"
                :loading="first ? 'eager' : 'lazy'"
                decoding="async"
                class="size-full object-cover"
            />
        </picture>

        <template v-if="hasText">
            <!-- The shade the words stand on: from below on phones, from the reading side on wide screens. -->
            <div aria-hidden="true" class="absolute inset-0 bg-linear-to-t from-(--night)/90 via-(--night)/45 to-transparent sm:hidden"></div>
            <div
                aria-hidden="true"
                class="absolute inset-0 hidden from-(--night)/85 via-(--night)/40 to-transparent sm:block ltr:bg-linear-to-r rtl:bg-linear-to-l"
            ></div>

            <div class="relative mx-auto flex h-full w-full max-w-310 items-end px-5 pt-10 pb-24 sm:items-center sm:px-6 sm:pb-10">
                <div class="max-w-xl border-s-2 border-(--gold) ps-5 sm:ps-9">
                    <p
                        v-if="slide.eyebrow"
                        class="hero-rise text-sm font-semibold text-(--gold) sm:text-base ltr:tracking-[0.18em] ltr:uppercase"
                        style="--hero-delay: 120ms"
                    >
                        {{ slide.eyebrow }}
                    </p>
                    <component
                        :is="Heading"
                        v-if="slide.title"
                        class="hero-rise mt-3 text-[2.25rem] leading-[1.25] font-bold text-balance text-white sm:text-6xl ltr:leading-[1.05]"
                        style="--hero-delay: 240ms"
                    >
                        {{ slide.title }}
                    </component>
                    <p v-if="slide.subtitle" class="hero-rise mt-4 max-w-md text-base leading-relaxed text-white/85 sm:text-lg" style="--hero-delay: 380ms">
                        {{ slide.subtitle }}
                    </p>
                    <div v-if="slide.url" class="hero-rise mt-8" style="--hero-delay: 520ms">
                        <CampaignLink
                            :href="slide.url"
                            class="group inline-flex min-h-12 items-center gap-2 rounded-full bg-(--cream) px-8 text-[0.875rem] font-semibold text-(--night) shadow-lg shadow-black/20 transition-[background-color,transform] duration-300 hover:-translate-y-0.5 hover:bg-(--gold) ltr:tracking-[0.1em] ltr:uppercase"
                        >
                            {{ slide.button || t('Shop now') }}
                            <span class="inline-block transition-transform duration-300 group-hover:translate-x-1 rtl:group-hover:-translate-x-1"><ArrowMark /></span>
                        </CampaignLink>
                    </div>
                </div>
            </div>
        </template>

        <!-- A designed banner: the whole picture leads on. -->
        <CampaignLink v-else-if="slide.url" :href="slide.url" class="absolute inset-0" :aria-label="slide.title || t('Shop now')" />
    </div>
</template>
