<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The message a storefront form leaves after it is saved ("Your details were saved."). */
const page = usePage();
const message = computed(() => (page.props.flash as { success?: string | null } | undefined)?.success ?? null);
</script>

<template>
    <p v-if="message" role="status" class="rounded-lg border border-store-200 bg-store-50 px-4 py-3 font-bold text-store-800">{{ message }}</p>
</template>
