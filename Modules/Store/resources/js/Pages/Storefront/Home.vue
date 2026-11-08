<script setup lang="ts">
import HomeCategories from '@/Components/storefront/HomeCategories.vue';
import HomeFeatured from '@/Components/storefront/HomeFeatured.vue';
import HomeHero from '@/Components/storefront/HomeHero.vue';
import HomeMembership from '@/Components/storefront/HomeMembership.vue';
import HomePromises from '@/Components/storefront/HomePromises.vue';
import HomeSignature from '@/Components/storefront/HomeSignature.vue';
import HomeStory from '@/Components/storefront/HomeStory.vue';
import HomeValues from '@/Components/storefront/HomeValues.vue';
import type { BrandValueText, StoreCard, StoreCategory } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    hero: { eyebrow: string | null; title: string; subtitle: string | null; image: string | null };
    promises: { key: string; title: string; text: string; url: string | null }[];
    categories: StoreCategory[];
    signature: StoreCard | null;
    featured: StoreCard[];
    heroPiece: StoreCard | null;
    story: { title: string | null; text: string; image: string | null; about: string | null } | null;
    values: BrandValueText[];
    membership: { title: string; text: string } | null;
}>();
</script>

<template>
    <HomeHero :hero="hero" :piece="heroPiece" :has-signature="signature !== null" :has-story="story !== null" />
    <HomePromises v-if="promises.length" :promises="promises" />
    <HomeCategories v-if="categories.length" :categories="categories" />
    <HomeSignature v-if="signature" :card="signature" />
    <HomeFeatured v-if="featured.length" :cards="featured" />
    <HomeStory v-if="story" :story="story" />
    <HomeValues v-if="values.length" :values="values" />
    <HomeMembership v-if="membership" :membership="membership" />
</template>
