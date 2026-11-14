<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { btnBrand, btnOutline } from '@/Components/storefront/styles';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { Check } from '@/lib/icons';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    order: { number: string; total_label: string; status: string; awaiting_confirmation: boolean; track_url: string };
}>();
</script>

<template>
    <div class="mx-auto max-w-2xl px-4 pt-12 pb-20">
        <section role="status" class="rounded-lg border border-line bg-white p-8 text-center sm:p-12">
            <span aria-hidden="true" class="mx-auto grid size-16 place-items-center rounded-full bg-store-50 text-store-700"><Check class="size-8" /></span>
            <h1 class="mt-5 text-2xl font-bold sm:text-3xl">{{ t('Thank you for your order') }}</h1>
            <p class="mt-3 text-(--ink-soft)">{{ t('Your order number') }}</p>
            <p class="mt-1 text-3xl font-bold tracking-wide" dir="ltr">{{ order.number }}</p>
            <p class="mt-4">{{ t('Total') }}: <strong>{{ order.total_label }}</strong></p>
            <p v-if="order.awaiting_confirmation" class="mx-auto mt-4 max-w-md leading-7 text-(--ink-soft)">
                {{ t('We will call you soon to confirm your order. You pay in cash when it arrives.') }}
            </p>
            <p class="mx-auto mt-2 max-w-md text-sm text-muted">{{ t('Keep this number: with your phone it lets you follow your order.') }}</p>
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                <Link :href="order.track_url" :class="btnBrand">{{ t('Track your order') }}</Link>
                <Link :href="route('store.shop')" :class="btnOutline">{{ t('Continue shopping') }}</Link>
            </div>
        </section>
    </div>
</template>
