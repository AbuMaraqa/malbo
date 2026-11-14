<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { DollarSign, Returns, ShieldCheck, Truck } from '@/lib/icons';

import { container } from './styles';

const props = defineProps<{ promises: { key: string; title: string; text: string; url: string | null }[] }>();

const icons: Record<string, Component> = { shipping: Truck, returns: Returns, whatsapp: DollarSign, privacy: ShieldCheck };
const grid = computed(() => ['', 'sm:grid-cols-1', 'sm:grid-cols-2', 'sm:grid-cols-3', 'sm:grid-cols-2 lg:grid-cols-4'][props.promises.length] ?? '');
</script>

<template>
    <section data-section="promises" :aria-label="t('Our promises')" :class="container" class="mt-14">
        <ul class="reveal grid gap-4" :class="grid">
            <li v-for="promise in promises" :key="promise.key" :data-promise="promise.key">
                <component
                    :is="promise.url ? Link : 'div'"
                    :href="promise.url ?? undefined"
                    class="group flex h-full items-center gap-4 rounded-lg border border-line p-5 transition-colors"
                    :class="{ 'hover:border-store': promise.url }"
                >
                    <span class="grid size-13 shrink-0 place-items-center rounded-full bg-store-50 text-store-600 transition-colors group-hover:bg-store group-hover:text-store-contrast">
                        <component :is="icons[promise.key] ?? ShieldCheck" class="size-6" aria-hidden="true" />
                    </span>
                    <span class="min-w-0">
                        <span class="block font-semibold">{{ promise.title }}</span>
                        <span class="mt-0.5 block text-sm leading-6 text-muted">{{ promise.text }}</span>
                    </span>
                </component>
            </li>
        </ul>
    </section>
</template>
