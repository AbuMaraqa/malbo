<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { Check, Returns, Truck } from '@/lib/icons';

/** Links to the policies the store published, and how ordering works. */
defineProps<{ shipping?: string; returns?: string; hasWhatsapp: boolean }>();
</script>

<template>
    <ul v-if="shipping || returns || hasWhatsapp" class="mt-6 grid gap-2 rounded-lg border border-line bg-white p-4 text-sm sm:grid-cols-3" :aria-label="t('Buying with confidence')">
        <li v-if="shipping" data-trust="shipping">
            <Link :href="shipping" class="flex items-center gap-2 hover:text-store-700"><Truck class="size-5 shrink-0 text-store-700" aria-hidden="true" />{{ t('Delivery details') }}</Link>
        </li>
        <li v-if="returns" data-trust="returns">
            <Link :href="returns" class="flex items-center gap-2 hover:text-store-700"><Returns class="size-5 shrink-0 text-store-700" aria-hidden="true" />{{ t('Easy returns') }}</Link>
        </li>
        <li v-if="hasWhatsapp" data-trust="whatsapp" class="flex items-center gap-2"><Check class="size-5 shrink-0 text-store-700" aria-hidden="true" />{{ t('Confirm by WhatsApp, pay on delivery') }}</li>
    </ul>
</template>
