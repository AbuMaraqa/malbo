<script setup lang="ts">
import { btnDark, btnLg } from '../styles';
import CampaignLink from './CampaignLink.vue';

/** A call to action: a heading, a line and one button. */
defineProps<{ block: { heading: string | null; text: string | null; button_label: string; link: string | null } }>();
</script>

<template>
    <section class="rounded-lg border border-line bg-(--sand-light) px-6 py-10 text-center">
        <h2 v-if="block.heading" class="text-2xl font-bold">{{ block.heading }}</h2>
        <p v-if="block.text" class="mx-auto mt-2 max-w-xl text-[17px] leading-7 text-(--ink-soft)">{{ block.text }}</p>
        <CampaignLink v-if="block.link" :href="block.link" :class="[btnDark, btnLg]" class="mt-6">{{ block.button_label }}</CampaignLink>
    </section>
</template>
