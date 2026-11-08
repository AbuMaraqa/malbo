<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ status: number; home?: string }>();

const text = computed(() => ({
    title: {
        403: t('You do not have access to this page'),
        404: t('This page does not exist'),
        419: t('This page expired. Reload it and try again'),
        429: t('Too many requests. Wait a moment and try again'),
        503: t('The site is under maintenance'),
    }[props.status] ?? t('Something went wrong on our side'),
}));
</script>

<template>
    <Head :title="text.title" />
    <main class="flex min-h-screen flex-col items-center justify-center bg-surface px-4 text-center">
        <p class="tabular text-5xl font-semibold text-muted">{{ status }}</p>
        <h1 class="mt-3 text-lg font-semibold">{{ text.title }}</h1>
        <Button v-if="home" class="mt-5" :href="home" external>{{ t('Back to home') }}</Button>
    </main>
</template>
