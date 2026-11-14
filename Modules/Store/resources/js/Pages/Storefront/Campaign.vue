<script setup lang="ts">
import CampaignBanner from '@/Components/storefront/campaign/CampaignBanner.vue';
import CampaignCountdown from '@/Components/storefront/campaign/CampaignCountdown.vue';
import CampaignCta from '@/Components/storefront/campaign/CampaignCta.vue';
import CampaignImage from '@/Components/storefront/campaign/CampaignImage.vue';
import CampaignProducts from '@/Components/storefront/campaign/CampaignProducts.vue';
import InstagramGrid from '@/Components/storefront/InstagramGrid.vue';
import { container, prose } from '@/Components/storefront/styles';
import type { InstagramPostCard, StoreCard } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

/** A block as Modules\Store\Services\CampaignPageView sends it: its type decides its fields. */
type Block =
    | { id: number; type: 'text'; html: string }
    | { id: number; type: 'image'; image: string; alt: string; caption: string | null; link: string | null }
    | { id: number; type: 'banner'; image: string; heading: string | null; text: string | null; button_label: string | null; link: string | null }
    | { id: number; type: 'products'; heading: string | null; cards: StoreCard[]; more_url: string | null }
    | { id: number; type: 'cta'; heading: string | null; text: string | null; button_label: string; link: string | null }
    | { id: number; type: 'countdown'; heading: string | null; text: string | null; ends_at: string; seconds_left: number }
    | { id: number; type: 'instagram'; heading: string; posts: InstagramPostCard[] };

defineProps<{
    campaign: { title: string; preview: boolean; ends_at: string | null };
    blocks: Block[];
}>();
</script>

<template>
    <div v-if="campaign.preview" role="status" class="bg-(--warning,#b45309) px-4 py-2 text-center text-sm font-semibold text-white">
        {{ t('Preview: only staff see this page now (a draft, or outside its dates).') }}
    </div>

    <div :class="container" class="space-y-14 pt-10 pb-20">
        <h1 class="text-center text-3xl leading-tight font-bold sm:text-[2.5rem]">{{ campaign.title }}</h1>

        <template v-for="block in blocks" :key="block.id">
            <div v-if="block.type === 'text'" class="mx-auto max-w-3xl" :class="prose" v-html="block.html"></div>
            <CampaignImage v-else-if="block.type === 'image'" :block="block" />
            <CampaignBanner v-else-if="block.type === 'banner'" :block="block" />
            <CampaignProducts v-else-if="block.type === 'products'" :block="block" />
            <CampaignCta v-else-if="block.type === 'cta'" :block="block" />
            <CampaignCountdown v-else-if="block.type === 'countdown'" :block="block" />
            <InstagramGrid v-else-if="block.type === 'instagram'" :title="block.heading" :posts="block.posts" />
        </template>
    </div>
</template>
