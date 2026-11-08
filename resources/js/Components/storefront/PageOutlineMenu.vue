<script setup lang="ts">
import { t } from '@/lib/i18n';

import { helpLink } from './styles';

/** "On this page": folded above the text on phones, or a card beside it on desktop. */
defineProps<{ toc: { id: string; title: string }[]; variant: 'fold' | 'card' }>();
</script>

<template>
    <details v-if="variant === 'fold'" class="mb-8 rounded-lg border border-line bg-white lg:hidden">
        <summary class="flex min-h-12 cursor-pointer items-center px-5 font-bold">{{ t('On this page') }}</summary>
        <ol class="space-y-1 border-t border-line px-5 py-4 text-[15px]">
            <li v-for="item in toc" :key="item.id">
                <a :href="`#${item.id}`" class="block py-1 text-(--ink-soft) hover:text-store-700">{{ item.title }}</a>
            </li>
        </ol>
    </details>
    <nav v-else aria-labelledby="toc-title" class="hidden rounded-lg border border-line bg-white p-5 lg:block">
        <h2 id="toc-title" class="text-[15px] font-bold">{{ t('On this page') }}</h2>
        <ol class="mt-3 space-y-0.5 text-[15px]">
            <li v-for="item in toc" :key="item.id"><a :href="`#${item.id}`" :class="helpLink">{{ item.title }}</a></li>
        </ol>
    </nav>
</template>
