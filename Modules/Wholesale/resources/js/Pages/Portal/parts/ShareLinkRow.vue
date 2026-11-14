<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import { btnOutline } from '@/Components/storefront/styles';
import WhatsAppIcon from '@/Components/storefront/WhatsAppIcon.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { CatalogShareLink } from './types';

/** One shared catalog link: copy it, send it on WhatsApp, or revoke it. */
const props = defineProps<{ share: CatalogShareLink }>();

const copied = ref(false);

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.share.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        prompt(t('Copy the link'), props.share.url);
    }
}

function revoke(): void {
    if (confirm(t('Revoke this link? Whoever has it can no longer open the catalog.'))) {
        router.delete(route('store.wholesale.catalog-pdf.shares.destroy', props.share.id), { preserveScroll: true });
    }
}
</script>

<template>
    <li class="flex flex-wrap items-center justify-between gap-3 py-4">
        <div class="min-w-0">
            <p class="font-bold">{{ share.category ?? t('All categories') }}</p>
            <p class="text-sm" :class="share.prices === 'wholesale' ? 'font-semibold text-(--danger)' : 'text-(--ink-soft)'">
                {{ share.prices === 'wholesale' ? t('Shows your purchase prices') : t('Retail prices') }}
            </p>
            <p class="text-sm text-(--ink-soft)">{{ t('Works until :date', { date: share.expires_at }) }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" :class="btnOutline" @click="copy">{{ copied ? t('Copied') : t('Copy the link') }}</button>
            <a :href="share.whatsapp_url" target="_blank" rel="noopener" :class="btnOutline"><WhatsAppIcon class="size-4" />{{ t('Share on WhatsApp') }}</a>
            <a :href="share.url" target="_blank" rel="noopener" :class="btnOutline">{{ t('Open the PDF') }}</a>
            <button type="button" class="text-sm font-semibold text-(--danger) hover:underline" @click="revoke">{{ t('Revoke') }}</button>
        </div>
    </li>
</template>
