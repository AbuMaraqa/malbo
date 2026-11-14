<script setup lang="ts">
import { t } from '@/lib/i18n';

/** What a reorder just did: how many lines went into the cart, and each line it skipped or lowered, with why. */
defineProps<{ result: { added: number; skipped: string[] } }>();
</script>

<template>
    <section class="rounded-lg border border-line bg-(--sand-light) p-4 text-sm" role="status" aria-live="polite">
        <p class="font-bold">{{ t('Reorder: :count lines added to the cart. Review them before checkout.', { count: result.added }) }}</p>
        <template v-if="result.skipped.length">
            <p class="mt-2 font-semibold text-(--danger)">{{ t('Not added as ordered:') }}</p>
            <ul class="mt-1 list-disc space-y-0.5 ps-5">
                <li v-for="(line, index) in result.skipped" :key="index">{{ line }}</li>
            </ul>
        </template>
    </section>
</template>
