<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { defineAsyncComponent, ref, watch } from 'vue';

import { t } from '@/lib/i18n';
import { ShieldCheck } from '@/lib/icons';

import ReviewForm from './ReviewForm.vue';
import SectionHeading from './SectionHeading.vue';
import StarRating from './StarRating.vue';
import type { ReviewFormState, ReviewSummary, StoreReview, StoreReviewPage } from './types';

const ProductLightbox = defineAsyncComponent(() => import('./ProductLightbox.vue'));

/**
 * The product page's reviews: the average and count, approved reviews a page at a time (each new
 * page is added below the last), their photos full screen, the store's replies, and the form for
 * a buyer whose order was delivered.
 */
const props = defineProps<{ name: string; reviews: StoreReviewPage; summary: ReviewSummary; form: ReviewFormState }>();

const shown = ref<StoreReview[]>([...props.reviews.data]);
const loading = ref(false);
const lightbox = ref<{ images: string[]; start: number } | null>(null);

watch(
    () => props.reviews,
    (page) => {
        shown.value = page.meta.current_page === 1 ? [...page.data] : [...shown.value, ...page.data.filter((review) => !shown.value.some((seen) => seen.id === review.id))];
    },
);

function more(): void {
    loading.value = true;
    router.reload({
        data: { reviews_page: props.reviews.meta.current_page + 1 },
        only: ['reviews'],
        preserveUrl: true,
        showProgress: false,
        onFinish: () => (loading.value = false),
    });
}
</script>

<template>
    <section id="reviews" class="mt-20 scroll-mt-36" aria-labelledby="reviews-title">
        <SectionHeading id="reviews-title" :title="t('Reviews')" />

        <div class="mt-8 grid gap-10 lg:grid-cols-[18rem_1fr]">
            <div>
                <template v-if="summary.count">
                    <p class="text-5xl font-bold">{{ summary.average?.toFixed(1) }}</p>
                    <StarRating :rating="summary.average ?? 0" size="lg" class="mt-2" />
                    <p class="mt-1 text-sm text-(--ink-soft)">{{ t(':count reviews from verified buyers', { count: summary.count }) }}</p>
                </template>
                <p v-else class="text-(--ink-soft)">{{ t('No reviews yet.') }}</p>

                <div class="mt-6">
                    <template v-if="form.eligible">
                        <p v-if="form.review" class="mb-3 text-sm text-(--ink-soft)">{{ t('Your review') }}: <strong>{{ form.review.status_label }}</strong></p>
                    </template>
                    <p v-else-if="!form.signed_in" class="text-sm text-(--ink-soft)">
                        <a :href="form.login_url" class="font-bold text-store-700 underline underline-offset-4">{{ t('Sign in') }}</a>
                        {{ t('to review a product you received.') }}
                    </p>
                    <p v-else class="text-sm text-(--ink-soft)">{{ t('Buyers can review a product once their order with it is delivered.') }}</p>
                </div>
            </div>

            <div class="grid gap-8">
                <ReviewForm v-if="form.eligible" :submit-url="form.submit_url" :review="form.review" />

                <ul v-if="shown.length" class="divide-y divide-line">
                    <li v-for="review in shown" :key="review.id" class="py-6 first:pt-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <StarRating :rating="review.rating" size="sm" />
                            <span class="font-semibold">{{ review.author }}</span>
                            <span v-if="review.verified" class="inline-flex items-center gap-1 text-xs font-semibold text-[#15803d]"><ShieldCheck class="size-4" aria-hidden="true" />{{ t('Verified purchase') }}</span>
                            <span class="text-sm text-muted">{{ review.date }}</span>
                        </div>
                        <p v-if="review.title" class="mt-2 font-bold">{{ review.title }}</p>
                        <p class="mt-1 leading-7 whitespace-pre-line text-(--ink-soft)">{{ review.body }}</p>
                        <div v-if="review.photos.length" class="mt-3 flex flex-wrap gap-2">
                            <button
                                v-for="(photo, index) in review.photos"
                                :key="photo.id"
                                type="button"
                                class="cursor-zoom-in overflow-hidden rounded-md border border-line"
                                :aria-label="t('Open photo :number', { number: index + 1 })"
                                @click="lightbox = { images: review.photos.map((item) => item.url), start: index }"
                            >
                                <img :src="photo.url" alt="" loading="lazy" class="size-20 object-cover" />
                            </button>
                        </div>
                        <div v-if="review.reply" class="mt-4 border-s-2 border-store ps-4">
                            <p class="text-sm font-bold">{{ t('Reply from the store') }}</p>
                            <p class="mt-1 text-sm leading-6 whitespace-pre-line text-(--ink-soft)">{{ review.reply }}</p>
                        </div>
                    </li>
                </ul>

                <button v-if="reviews.meta.current_page < reviews.meta.last_page" type="button" class="justify-self-center text-sm font-bold underline underline-offset-4" :disabled="loading" @click="more">
                    {{ loading ? t('Loading…') : t('More reviews') }}
                </button>
            </div>
        </div>

        <ProductLightbox v-if="lightbox" :images="lightbox.images" :start="lightbox.start" :name="name" @close="lightbox = null" />
    </section>
</template>
