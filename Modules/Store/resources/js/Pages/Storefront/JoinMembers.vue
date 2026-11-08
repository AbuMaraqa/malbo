<script setup lang="ts">
import HomeMembership from '@/Components/storefront/HomeMembership.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';

defineOptions({ layout: StorefrontLayout });

/** The members section on its own page, for a link the store can share. */
defineProps<{ membership: { title: string; text: string } }>();
</script>

<template>
    <HomeMembership :membership="membership" />
</template>
