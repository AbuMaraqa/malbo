<script setup lang="ts">
import { prose } from './styles';

/** Questions that open, one at a time. The answers are sanitized on the server (RichText). */
defineProps<{ faq: { intro: string; items: { question: string; answer: string }[] } }>();
</script>

<template>
    <div v-if="faq.intro" class="mb-8" :class="prose" v-html="faq.intro"></div>
    <div data-faq class="space-y-3">
        <details
            v-for="(item, index) in faq.items"
            :key="index"
            name="faq"
            :open="index === 0"
            class="group rounded-lg border border-line bg-white open:border-store-200 open:shadow-sm"
        >
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-3 text-[17px] font-bold [&::-webkit-details-marker]:hidden">
                <span>{{ item.question }}</span>
                <span
                    aria-hidden="true"
                    class="grid size-8 shrink-0 place-items-center rounded-full bg-(--sand) text-xl leading-none transition group-open:rotate-45 group-open:bg-store group-open:text-store-contrast motion-reduce:transition-none"
                >+</span>
            </summary>
            <div v-if="item.answer" class="px-5 pb-5" :class="prose" v-html="item.answer"></div>
        </details>
    </div>
</template>
