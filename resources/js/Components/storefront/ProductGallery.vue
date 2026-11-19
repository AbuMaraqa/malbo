<script setup lang="ts">
import { computed, defineAsyncComponent, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { Play, ZoomIn } from '@/lib/icons';

import InitialTile from './InitialTile.vue';
import type { ProductVideo } from './types';

/** The zoomable full-screen view downloads only when a visitor opens it. */
const ProductLightbox = defineAsyncComponent(() => import('./ProductLightbox.vue'));

/**
 * The product's images, then its video as the last slide. A click on an image opens it full screen
 * and zoomable. YouTube plays from youtube-nocookie.com and Vimeo with "do not track".
 */
const props = defineProps<{ name: string; images: string[]; video?: ProductVideo | null }>();

const active = ref(0);
const lightbox = ref<number | null>(null);

const slides = computed(() => props.images.length + (props.video ? 1 : 0));
const showsVideo = computed(() => !!props.video && active.value === props.images.length);
const current = computed(() => props.images[active.value] ?? props.images[0] ?? null);

// Another variation or color brings its own images first: show the first. Compared by content, as
// the page builds a new list on every choice.
watch(
    () => props.images.join(' '),
    () => {
        active.value = 0;
    },
);
</script>

<template>
    <div class="min-w-0">
        <div class="relative aspect-4/5 overflow-hidden rounded-lg bg-(--sand)">
            <template v-if="showsVideo && video">
                <video v-if="video.type === 'file'" :src="video.src" controls playsinline preload="metadata" class="size-full bg-black object-contain"></video>
                <iframe
                    v-else
                    :src="video.src"
                    :title="t('Video of :name', { name })"
                    loading="lazy"
                    allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                    allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"
                    class="size-full border-0 bg-black"
                ></iframe>
            </template>
            <button v-else-if="current" type="button" class="group block size-full cursor-zoom-in" :aria-label="t('Zoom in')" @click="lightbox = active">
                <img :src="current" :alt="name" class="size-full object-cover" />
                <span class="absolute end-3 bottom-3 grid size-10 place-items-center rounded-full bg-(--shell) text-ink shadow-sm transition group-hover:scale-105" aria-hidden="true">
                    <ZoomIn class="size-5" />
                </span>
            </button>
            <InitialTile v-else :name="name" class="text-7xl" />
        </div>

        <ul v-if="slides > 1" class="mt-3 flex gap-2 overflow-x-auto" :aria-label="t('Product images')">
            <li v-for="(image, index) in images" :key="image" class="shrink-0">
                <button
                    type="button"
                    class="block size-18 cursor-pointer overflow-hidden rounded-md border-2"
                    :class="active === index ? 'border-store' : 'border-transparent'"
                    :aria-pressed="active === index"
                    @click="active = index"
                >
                    <img :src="image" :alt="t('Image :n', { n: index + 1 })" loading="lazy" class="size-full object-cover" />
                </button>
            </li>
            <li v-if="video" class="shrink-0">
                <button
                    type="button"
                    class="grid size-18 cursor-pointer place-items-center rounded-md border-2 bg-(--night) text-white"
                    :class="showsVideo ? 'border-store' : 'border-transparent'"
                    :aria-pressed="showsVideo"
                    @click="active = images.length"
                >
                    <Play class="size-7" aria-hidden="true" />
                    <span class="sr-only">{{ t('Video') }}</span>
                </button>
            </li>
        </ul>

        <ProductLightbox v-if="lightbox !== null && images.length" :images="images" :start="lightbox" :name="name" @close="lightbox = null" />
    </div>
</template>
