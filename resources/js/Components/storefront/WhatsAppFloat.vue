<script setup lang="ts">
import { t } from '@/lib/i18n';

import WhatsAppIcon from './WhatsAppIcon.vue';

defineProps<{ number: string; hideOnPhones?: boolean }>();
</script>

<template>
    <a
        :href="`https://wa.me/${number}`"
        target="_blank"
        rel="noopener"
        data-whatsapp-float
        :aria-label="t('Chat with us on WhatsApp')"
        class="fixed end-5 bottom-5 z-40 grid size-14 place-items-center rounded-full bg-[#1F8A4C] text-white shadow-lg shadow-black/15 transition hover:scale-105 motion-reduce:transition-none"
        :class="{ 'max-lg:hidden': hideOnPhones }"
    >
        <WhatsAppIcon class="size-7" />
    </a>
</template>
