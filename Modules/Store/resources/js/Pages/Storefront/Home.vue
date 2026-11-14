<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import HomeArrivals from '@/Components/storefront/HomeArrivals.vue';
import HomeCategories from '@/Components/storefront/HomeCategories.vue';
import HomeFeatured from '@/Components/storefront/HomeFeatured.vue';
import HomeHero from '@/Components/storefront/HomeHero.vue';
import HomeMembership from '@/Components/storefront/HomeMembership.vue';
import HomePromises from '@/Components/storefront/HomePromises.vue';
import HomeSignature from '@/Components/storefront/HomeSignature.vue';
import HomeStory from '@/Components/storefront/HomeStory.vue';
import HomeValues from '@/Components/storefront/HomeValues.vue';
import HomeWholesale from '@/Components/storefront/HomeWholesale.vue';
import InstagramGrid from '@/Components/storefront/InstagramGrid.vue';
import { container } from '@/Components/storefront/styles';
import type { BrandValueText, InstagramPostCard, StoreCard, StoreCategory, StoreLayoutProps } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    promises: { key: string; title: string; text: string; url: string | null }[];
    categories: StoreCategory[];
    signature: StoreCard | null;
    featured: StoreCard[];
    arrivals: StoreCard[];
    heroPiece: StoreCard | null;
    story: { title: string | null; text: string; image: string | null; about: string | null } | null;
    values: BrandValueText[];
    membership: { title: string; text: string } | null;
    /** Instagram posts, once staff switch the section on. */
    instagram: { title: string; profile_url: string | null; posts: InstagramPostCard[] } | null;
}>();

// The way into the wholesale portal comes with the layout's shared `store` prop.
const wholesale = computed(() => (usePage().props.store as StoreLayoutProps).wholesale);
</script>

<template>
    <HomeHero :hero="hero" :piece="heroPiece" :has-signature="signature !== null" :has-story="story !== null" />
    <HomePromises v-if="promises.length" :promises="promises" />
    <HomeCategories v-if="categories.length" :categories="categories" />
    <HomeArrivals v-if="arrivals.length" :cards="arrivals" />
    <HomeSignature v-if="signature" :card="signature" />
    <HomeFeatured v-if="featured.length" :cards="featured" />
    <HomeWholesale v-if="wholesale" :wholesale="wholesale" />
    <HomeStory v-if="story" :story="story" />
    <HomeValues v-if="values.length" :values="values" />
    <section v-if="instagram" data-section="instagram" :class="container" class="reveal mt-20">
        <InstagramGrid :title="instagram.title" :profile-url="instagram.profile_url" :posts="instagram.posts" />
    </section>
    <HomeMembership v-if="membership" :membership="membership" />
</template>
