<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import HomeCategories from '@/Components/storefront/HomeCategories.vue';
import HomeDeals from '@/Components/storefront/HomeDeals.vue';
import HomeHero from '@/Components/storefront/HomeHero.vue';
import HomeMembership from '@/Components/storefront/HomeMembership.vue';
import HomeProductTabs from '@/Components/storefront/HomeProductTabs.vue';
import HomePromises from '@/Components/storefront/HomePromises.vue';
import HomeSignature from '@/Components/storefront/HomeSignature.vue';
import HomeStory from '@/Components/storefront/HomeStory.vue';
import HomeValues from '@/Components/storefront/HomeValues.vue';
import HomeWholesale from '@/Components/storefront/HomeWholesale.vue';
import InstagramGrid from '@/Components/storefront/InstagramGrid.vue';
import type { BrandValueText, InstagramPostCard, StoreCard, StoreCategory, StoreLayoutProps } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    promises: { key: string; title: string; text: string; url: string | null }[];
    categories: StoreCategory[];
    signature: StoreCard | null;
    featured: StoreCard[];
    arrivals: StoreCard[];
    bestSellers: StoreCard[];
    story: { title: string | null; text: string; image: string | null; about: string | null } | null;
    values: BrandValueText[];
    membership: { title: string; text: string } | null;
    /** Instagram posts, once staff switch the section on. */
    instagram: { title: string; profile_url: string | null; posts: InstagramPostCard[] } | null;
}>();

// The way into the wholesale portal comes with the layout's shared `store` prop.
const wholesale = computed(() => (usePage().props.store as StoreLayoutProps).wholesale);

// The hero's extra slides: the store's picks with a photo.
const heroPieces = computed(() => [props.signature, ...props.featured].filter((card): card is StoreCard => card !== null));

// "Today's offers": every discounted piece the page already has, each once.
const deals = computed(() => {
    const seen = new Set<number>();

    return [props.signature, ...props.arrivals, ...props.bestSellers, ...props.featured].filter((card): card is StoreCard => {
        if (!card || !card.price.discounted || seen.has(card.id)) return false;
        seen.add(card.id);

        return true;
    });
});
</script>

<template>
    <HomeHero :hero="hero" :pieces="heroPieces" />
    <HomeCategories v-if="categories.length" :categories="categories" />
    <HomeProductTabs v-if="arrivals.length" :arrivals="arrivals" :best-sellers="bestSellers" :featured="featured" />
    <HomeSignature v-if="signature" :card="signature" />
    <HomeDeals v-if="deals.length >= 2" :cards="deals" />
    <HomePromises v-if="promises.length" :promises="promises" />
    <HomeStory v-if="story" :story="story" />
    <HomeValues v-if="values.length" :values="values" />
    <HomeWholesale v-if="wholesale" :wholesale="wholesale" />
    <HomeMembership v-if="membership" :membership="membership" />
    <section v-if="instagram" data-section="instagram" class="reveal mt-24">
        <InstagramGrid :title="instagram.title" :profile-url="instagram.profile_url" :posts="instagram.posts" />
    </section>
</template>
