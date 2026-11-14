<script setup lang="ts">
import { computed, ref } from 'vue';

import { t } from '@/lib/i18n';

import { prose } from './styles';
import type { ProductDetail } from './types';

/**
 * Under the product: its description and its details (fabric, composition, fit, care, the model in
 * the photos), as tabs when it has both.
 */
const props = defineProps<{ details: ProductDetail[]; description: string | null }>();

type Tab = 'description' | 'details';

const tabs = computed(() => {
    const list: { key: Tab; label: string }[] = [];

    if (props.description) list.push({ key: 'description', label: t('Description') });
    if (props.details.length) list.push({ key: 'details', label: t('Details') });

    return list;
});

const chosen = ref<Tab | null>(null);
const active = computed<Tab | undefined>(() => (tabs.value.some((tab) => tab.key === chosen.value) ? (chosen.value ?? undefined) : tabs.value[0]?.key));
</script>

<template>
    <section v-if="tabs.length" class="mt-16 overflow-hidden rounded-lg border border-line" :aria-label="t('Product information')">
        <div role="tablist" class="flex gap-1 border-b border-line bg-(--sand-light) px-2">
            <button
                v-for="tab in tabs"
                :id="`tab-${tab.key}`"
                :key="tab.key"
                type="button"
                role="tab"
                :aria-selected="active === tab.key"
                :aria-controls="`panel-${tab.key}`"
                class="relative cursor-pointer px-5 py-4 font-semibold transition-colors after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-store after:transition-transform"
                :class="active === tab.key ? 'text-store-600 after:scale-x-100' : 'text-muted after:scale-x-0 hover:text-ink'"
                @click="chosen = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <div v-if="description" v-show="active === 'description'" id="panel-description" role="tabpanel" aria-labelledby="tab-description" class="p-6 sm:p-8" :class="prose">
            <!-- Sanitized on the server (RichText). -->
            <div v-html="description"></div>
        </div>

        <div v-if="details.length" v-show="active === 'details'" id="panel-details" role="tabpanel" aria-labelledby="tab-details" class="p-6 sm:p-8">
            <dl class="divide-y divide-line">
                <div v-for="detail in details" :key="detail.key" class="grid gap-1 py-3.5 first:pt-0 last:pb-0 sm:grid-cols-[12rem_1fr] sm:gap-4">
                    <dt class="font-semibold">{{ detail.label }}</dt>
                    <dd class="leading-7 whitespace-pre-line text-(--ink-soft)">{{ detail.value }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
