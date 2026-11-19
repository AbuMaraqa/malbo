<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

import PageHero from '@/Components/storefront/PageHero.vue';
import StoreFlash from '@/Components/storefront/StoreFlash.vue';
import { btnBrand } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{ contact: string; done: boolean; confirmUrl: string }>();

const sending = ref(false);

function confirm(): void {
    sending.value = true;
    router.post(props.confirmUrl, {}, { preserveScroll: true, onFinish: () => (sending.value = false) });
}
</script>

<template>
    <PageHero :title="t('Stop these messages')" />
    <div class="mx-auto max-w-xl px-4 pt-12 pb-20">
        <StoreFlash />
        <section class="rounded-lg border border-line bg-(--shell) p-8 text-center">
            <p class="text-lg font-bold" dir="ltr">{{ contact }}</p>
            <p v-if="done" class="mt-4 leading-7 text-(--ink-soft)">{{ t('This contact gets no more reminders or offers from us.') }}</p>
            <template v-else>
                <p class="mt-4 leading-7 text-(--ink-soft)">{{ t('Stop cart reminders and back-in-stock messages to this contact?') }}</p>
                <button type="button" class="mt-6" :class="btnBrand" :disabled="sending" @click="confirm">{{ t('Stop these messages') }}</button>
            </template>
        </section>
    </div>
</template>
