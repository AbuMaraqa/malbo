<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import AccountShell from '@/Components/storefront/AccountShell.vue';
import StarRating from '@/Components/storefront/StarRating.vue';
import type { StoreReview } from '@/Components/storefront/types';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

defineProps<{
    reviews: (StoreReview & { status: string; status_label: string; product: { name: string; image: string | null; url: string | null } })[];
}>();

const tones: Record<string, string> = {
    approved: 'bg-[#dcfce7] text-[#15803d]',
    pending: 'bg-(--sand) text-(--ink-soft)',
    rejected: 'bg-[#fee2e2] text-(--danger)',
};
</script>

<template>
    <AccountShell :title="t('My reviews')" :parents="[{ name: t('My account'), url: route('store.account') }]">
        <div v-if="!reviews.length" class="rounded-lg border border-line bg-white p-8 text-center">
            <p class="text-lg font-bold">{{ t('You have not reviewed anything yet') }}</p>
            <p class="mt-2 text-(--ink-soft)">{{ t('Once an order is delivered, you can review its products on their page.') }}</p>
        </div>
        <ul v-else class="grid gap-4">
            <li v-for="review in reviews" :key="review.id" class="rounded-lg border border-line bg-white p-5">
                <div class="flex items-start gap-4">
                    <img v-if="review.product.image" :src="review.product.image" alt="" class="size-16 shrink-0 rounded-md border border-line object-cover" />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <component :is="review.product.url ? Link : 'span'" :href="review.product.url ?? undefined" class="font-bold hover:text-store-600">{{ review.product.name }}</component>
                            <span class="rounded-full px-3 py-0.5 text-xs font-semibold" :class="tones[review.status]">{{ review.status_label }}</span>
                        </div>
                        <div class="mt-1 flex items-center gap-2">
                            <StarRating :rating="review.rating" size="sm" />
                            <span class="text-sm text-muted">{{ review.date }}</span>
                        </div>
                        <p v-if="review.title" class="mt-2 font-semibold">{{ review.title }}</p>
                        <p class="mt-1 leading-7 whitespace-pre-line text-(--ink-soft)">{{ review.body }}</p>
                        <div v-if="review.photos.length" class="mt-3 flex flex-wrap gap-2">
                            <img v-for="photo in review.photos" :key="photo.id" :src="photo.url" alt="" loading="lazy" class="size-16 rounded-md border border-line object-cover" />
                        </div>
                        <div v-if="review.reply" class="mt-3 border-s-2 border-store ps-4">
                            <p class="text-sm font-bold">{{ t('Reply from the store') }}</p>
                            <p class="mt-1 text-sm whitespace-pre-line text-(--ink-soft)">{{ review.reply }}</p>
                        </div>
                    </div>
                </div>
            </li>
        </ul>
    </AccountShell>
</template>
