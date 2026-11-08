<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import { glassStrong } from './styles';

const props = defineProps<{ promises: { key: string; title: string; text: string; url: string | null }[] }>();

const grid = computed(() => ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-2 lg:grid-cols-4'][props.promises.length] ?? '');
</script>

<template>
    <section data-section="promises" :aria-label="t('Our promises')" class="relative z-10 mx-auto -mt-12 max-w-310 px-4">
        <ul class="reveal grid overflow-hidden rounded-2xl" :class="[glassStrong, grid]">
            <li v-for="promise in promises" :key="promise.key" :data-promise="promise.key" class="px-6 py-5">
                <Link v-if="promise.url" :href="promise.url" class="group block">
                    <span class="block font-bold group-hover:text-store-700">{{ promise.title }}</span>
                    <span class="mt-1 block text-sm leading-6 text-(--ink-soft)">{{ promise.text }}</span>
                </Link>
                <template v-else>
                    <span class="block font-bold">{{ promise.title }}</span>
                    <span class="mt-1 block text-sm leading-6 text-(--ink-soft)">{{ promise.text }}</span>
                </template>
            </li>
        </ul>
    </section>
</template>
