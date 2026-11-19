<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { ShieldCheck } from '@/lib/icons';

import BrandSparkle from './BrandSparkle.vue';
import { container } from './styles';

/**
 * The store's promises under the hero, each with its MALBO illustration (public/brand/icons: maroon
 * line on a cream blob, gold sparkle): the gift box for careful delivery, the hanger for exchange,
 * the bag for paying on delivery. Privacy has no illustration in the brand book: a maroon line icon
 * on the same cream blob with the gold star.
 */
const props = defineProps<{ promises: { key: string; title: string; text: string; url: string | null }[] }>();

const illustrations: Record<string, string> = { shipping: 'gifts', returns: 'exchange', whatsapp: 'purchases' };
const grid = computed(() => ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-2 lg:grid-cols-4'][props.promises.length] ?? '');
</script>

<template>
    <section data-section="promises" :aria-label="t('Our promises')" :class="container" class="mt-14">
        <ul class="reveal grid gap-4" :class="grid">
            <li v-for="promise in promises" :key="promise.key" :data-promise="promise.key">
                <component
                    :is="promise.url ? Link : 'div'"
                    :href="promise.url ?? undefined"
                    class="group flex h-full items-center gap-4 rounded-2xl border border-line bg-(--shell) px-5 py-6 transition-colors"
                    :class="{ 'hover:border-store/40 hover:bg-(--sand-light)': promise.url }"
                >
                    <img v-if="illustrations[promise.key]" :src="`/brand/icons/${illustrations[promise.key]}.svg`" alt="" class="h-12 w-auto shrink-0" />
                    <span v-else aria-hidden="true" class="relative grid h-12 w-[4.75rem] shrink-0 place-items-center rounded-[50%] bg-(--sand) text-store">
                        <ShieldCheck class="size-6" />
                        <BrandSparkle class="absolute end-3 top-1 h-3 w-2.5 text-(--gold)" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[0.9375rem] font-semibold ltr:text-[0.875rem] ltr:tracking-[0.06em] ltr:uppercase">{{ promise.title }}</span>
                        <span class="mt-0.5 block text-sm leading-6 text-muted">{{ promise.text }}</span>
                    </span>
                </component>
            </li>
        </ul>
    </section>
</template>
