<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import ArrowMark from '../ArrowMark.vue';
import BrandMark from '../BrandMark.vue';
import BrandPetal from '../BrandPetal.vue';
import BrandSparkle from '../BrandSparkle.vue';

export interface ArchSlide {
    key: string;
    lead: string | null;
    title: string;
    note: string | null;
    image: string | null;
    url: string;
    action: string;
}

/**
 * The store's own slide, used while staff have not set up slides in the admin: words on the maroon
 * band with the MALBO pattern, the photo in an arch at the end (a rounded picture above the words on
 * phones), or the white MALBO mark without one.
 */
const props = defineProps<{ slide: ArchSlide; first: boolean }>();

const Heading = computed(() => (props.first ? 'h1' : 'h2'));
</script>

<template>
    <div class="absolute inset-0">
        <!-- A big petal echoes the logo behind the text; the photo stands in an arch at the end (the brand's arched forms). -->
        <BrandPetal aria-hidden="true" class="absolute -start-24 top-1/2 w-[46rem] -translate-y-1/2 text-white opacity-[0.06]" />
        <div class="absolute inset-y-10 end-6 hidden w-[min(calc(50%-3rem),34rem)] overflow-hidden rounded-t-[17rem] rounded-b-[2rem] lg:block">
            <img v-if="slide.image" :src="slide.image" alt="" :fetchpriority="first ? 'high' : undefined" class="hero-zoom size-full object-cover" />
            <div v-else class="grid size-full place-items-center bg-(--night-soft)">
                <BrandMark tone="white" class="h-[46%]" />
            </div>
        </div>
        <BrandSparkle aria-hidden="true" class="absolute end-[47%] top-[12%] hidden h-10 w-8 text-(--gold) lg:block" />

        <div class="relative mx-auto flex h-full w-full max-w-310 flex-col justify-center gap-7 px-4 py-10 sm:px-6 lg:flex-row lg:items-center lg:justify-start lg:py-0">
            <!-- Phones see the photo too, rounded above the words. -->
            <img
                v-if="slide.image"
                :src="slide.image"
                alt=""
                :fetchpriority="first ? 'high' : undefined"
                class="aspect-[16/10] w-full rounded-[1.75rem] object-cover lg:hidden"
            />
            <div class="max-w-2xl border-s-2 border-(--gold) ps-6 sm:ps-10 lg:w-1/2">
                <p v-if="slide.lead" class="hero-rise text-3xl leading-snug font-light text-white/90 sm:text-6xl sm:leading-tight" style="--hero-delay: 120ms">
                    {{ slide.lead }}
                </p>
                <component
                    :is="Heading"
                    class="hero-rise mt-2 text-[2.5rem] leading-[1.3] font-bold text-balance sm:text-7xl ltr:leading-[1.05] ltr:uppercase"
                    style="--hero-delay: 240ms"
                >
                    {{ slide.title }}
                </component>
                <p
                    v-if="slide.note"
                    class="hero-rise mt-6 text-base font-semibold text-(--gold) sm:text-lg ltr:tracking-[0.12em] ltr:uppercase"
                    style="--hero-delay: 380ms"
                >
                    {{ slide.note }}
                </p>
                <div class="hero-rise mt-9" style="--hero-delay: 520ms">
                    <Link
                        :href="slide.url"
                        class="inline-flex min-h-12 items-center gap-2 rounded-full bg-(--cream) px-9 text-[0.875rem] font-semibold text-(--night) transition-colors hover:bg-(--gold) ltr:tracking-[0.1em] ltr:uppercase"
                    >
                        {{ slide.action }} <ArrowMark />
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
