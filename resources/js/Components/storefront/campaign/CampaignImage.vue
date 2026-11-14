<script setup lang="ts">
import CampaignLink from './CampaignLink.vue';

/** A picture with its caption, linked when the block has a link. */
defineProps<{ block: { image: string; alt: string; caption: string | null; link: string | null } }>();
</script>

<template>
    <figure>
        <CampaignLink v-if="block.link" :href="block.link" class="block overflow-hidden rounded-lg">
            <img :src="block.image" :alt="block.alt" class="w-full object-cover" loading="lazy" />
        </CampaignLink>
        <img v-else :src="block.image" :alt="block.alt" class="w-full rounded-lg object-cover" loading="lazy" />
        <figcaption v-if="block.caption" class="mt-2 text-center text-sm text-muted">{{ block.caption }}</figcaption>
    </figure>
</template>
