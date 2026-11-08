<script setup lang="ts">
import { computed, ref, watch } from 'vue';

import { t } from '@/lib/i18n';

import InitialTile from './InitialTile.vue';

const props = defineProps<{ name: string; images: string[] }>();

const active = ref(0);
const current = computed(() => props.images[active.value] ?? props.images[0] ?? null);

// Another variation brings its own image first: show it.
watch(
    () => props.images,
    () => {
        active.value = 0;
    },
);
</script>

<template>
    <div class="min-w-0">
        <div class="aspect-4/5 overflow-hidden rounded-lg bg-(--sand-deep)">
            <img v-if="current" :src="current" :alt="name" class="size-full object-cover" />
            <InitialTile v-else :name="name" class="text-7xl" />
        </div>
        <ul v-if="images.length > 1" class="mt-3 flex gap-2 overflow-x-auto" :aria-label="t('Product images')">
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
        </ul>
    </div>
</template>
