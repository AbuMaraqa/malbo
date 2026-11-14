<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { btnBrand, btnOutline, card } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import PortalShell from './parts/PortalShell.vue';
import type { PortalLink } from './parts/types';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    nav: PortalLink[];
    order: { number: string; total_label: string; status: string; awaiting_confirmation: boolean; payment: string | null; url: string };
}>();
</script>

<template>
    <PortalShell :title="t('Thank you for your order')" :nav="nav">
        <section :class="card" class="mx-auto max-w-xl p-8 text-center">
            <p class="text-sm text-muted">{{ t('Order number') }}</p>
            <p class="mt-1 text-2xl font-bold" dir="ltr">{{ order.number }}</p>
            <p class="mt-4">{{ t('Total') }}: <strong>{{ order.total_label }}</strong></p>
            <p v-if="order.payment" class="mt-1 text-sm text-(--ink-soft)">{{ t('Payment terms') }}: {{ order.payment }}</p>
            <p v-if="order.awaiting_confirmation" class="mt-4 leading-7 text-(--ink-soft)">{{ t('MALBO will contact you to confirm the order and arrange its delivery.') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link :href="order.url" :class="btnBrand">{{ t('View the order') }}</Link>
                <Link :href="route('store.wholesale.catalog')" :class="btnOutline">{{ t('Wholesale catalog') }}</Link>
            </div>
        </section>
    </PortalShell>
</template>
