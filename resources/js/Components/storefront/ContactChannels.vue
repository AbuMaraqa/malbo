<script setup lang="ts">
import { t } from '@/lib/i18n';

import { btnBrand } from './styles';
import type { StoreChannels } from './types';

/** Only what the store set. */
defineProps<{ channels: StoreChannels }>();
</script>

<template>
    <div data-contact="channels" class="rounded-lg border border-line bg-white p-6">
        <h2 class="text-[17px] font-bold">{{ t('Reach us directly') }}</h2>
        <ul class="mt-4 divide-y divide-line">
            <li v-if="channels.whatsapp" data-contact="whatsapp" class="py-3 first:pt-0">
                <span class="block text-sm text-muted">{{ t('WhatsApp') }} · {{ t('The fastest way to reach us') }}</span>
                <a :href="`https://wa.me/${channels.whatsapp}`" target="_blank" rel="noopener" class="mt-1 inline-block text-lg font-bold text-store-700" dir="ltr">+{{ channels.whatsapp }}</a>
            </li>
            <li v-if="channels.phone" data-contact="phone" class="py-3 first:pt-0">
                <span class="block text-sm text-muted">{{ t('Phone') }}</span>
                <a :href="`tel:${channels.phone}`" class="mt-1 inline-block text-lg font-bold text-store-700" dir="ltr">{{ channels.phone }}</a>
            </li>
            <li v-if="channels.email" data-contact="email" class="py-3 first:pt-0">
                <span class="block text-sm text-muted">{{ t('Email') }}</span>
                <a :href="`mailto:${channels.email}`" class="mt-1 inline-block text-lg font-bold break-all text-store-700">{{ channels.email }}</a>
            </li>
            <li v-if="channels.address" data-contact="address" class="py-3 first:pt-0">
                <span class="block text-sm text-muted">{{ t('Address') }}</span>
                <p class="mt-1 leading-7">{{ channels.address }}</p>
            </li>
        </ul>
        <a v-if="channels.whatsapp" :href="`https://wa.me/${channels.whatsapp}`" target="_blank" rel="noopener" class="mt-4 w-full" :class="btnBrand">{{ t('Chat on WhatsApp') }}</a>
    </div>
</template>
