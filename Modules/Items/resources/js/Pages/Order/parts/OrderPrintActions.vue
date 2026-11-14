<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { Printer, Truck } from '@/lib/icons';
import { route } from '@/lib/route';

/**
 * The order's papers, each a PDF in a new tab: the invoice in the site's main language (Arabic),
 * the same invoice in the other languages, and the courier's waybill.
 */
const props = defineProps<{ orderId: number }>();

interface SiteLocale {
    code: string;
    native: string;
}

const page = usePage();
const others = computed(() => ((page.props.locales as SiteLocale[] | undefined) ?? []).slice(1));

// `locale` is a query parameter here, not the route's `{locale}`: added by hand, as route() would fill the path.
const invoiceUrl = (locale?: string): string => route('orders.invoice', props.orderId) + (locale ? `?locale=${encodeURIComponent(locale)}` : '');
</script>

<template>
    <div class="flex flex-wrap items-center gap-1">
        <Button external new-tab :href="invoiceUrl()"><Printer class="size-4" /> {{ t('Invoice') }}</Button>
        <Button
            v-for="locale in others"
            :key="locale.code"
            variant="ghost"
            size="sm"
            external
            new-tab
            :href="invoiceUrl(locale.code)"
            :aria-label="t('Invoice in :language', { language: locale.native })"
        >
            {{ locale.native }}
        </Button>
    </div>
    <Button external new-tab :href="route('orders.waybill', orderId)"><Truck class="size-4" /> {{ t('Waybill') }}</Button>
</template>
