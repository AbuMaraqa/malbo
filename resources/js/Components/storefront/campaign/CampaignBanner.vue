<script setup lang="ts">
import { btnBrand } from '../styles';
import CampaignLink from './CampaignLink.vue';

/** A wide picture with a heading, a line and a button over it. */
defineProps<{ block: { image: string; heading: string | null; text: string | null; button_label: string | null; link: string | null } }>();
</script>

<template>
    <section class="relative overflow-hidden rounded-lg bg-(--sand)">
        <img :src="block.image" :alt="block.heading ?? ''" class="h-72 w-full object-cover sm:h-96" />
        <div v-if="block.heading || block.text || (block.button_label && block.link)" class="absolute inset-0 flex items-end bg-gradient-to-t from-black/60 to-transparent p-6 sm:p-10">
            <div class="max-w-xl text-white">
                <h2 v-if="block.heading" class="text-2xl leading-tight font-bold sm:text-4xl">{{ block.heading }}</h2>
                <p v-if="block.text" class="mt-2 text-[17px] leading-7 text-white/90">{{ block.text }}</p>
                <CampaignLink v-if="block.button_label && block.link" :href="block.link" :class="btnBrand" class="mt-5">{{ block.button_label }}</CampaignLink>
            </div>
        </div>
    </section>
</template>
