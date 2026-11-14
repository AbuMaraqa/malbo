<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import BundleComponentPicker from '@/Components/storefront/BundleComponentPicker.vue';
import NotifyMeForm from '@/Components/storefront/NotifyMeForm.vue';
import PriceRow from '@/Components/storefront/PriceRow.vue';
import ProductBadges from '@/Components/storefront/ProductBadges.vue';
import ProductDetails from '@/Components/storefront/ProductDetails.vue';
import ProductGallery from '@/Components/storefront/ProductGallery.vue';
import ProductGrid from '@/Components/storefront/ProductGrid.vue';
import ProductOrderBar from '@/Components/storefront/ProductOrderBar.vue';
import ProductReviews from '@/Components/storefront/ProductReviews.vue';
import ProductTrustRow from '@/Components/storefront/ProductTrustRow.vue';
import QuantityStepper from '@/Components/storefront/QuantityStepper.vue';
import SectionHeading from '@/Components/storefront/SectionHeading.vue';
import StarRating from '@/Components/storefront/StarRating.vue';
import StoreBreadcrumb from '@/Components/storefront/StoreBreadcrumb.vue';
import { container } from '@/Components/storefront/styles';
import type {
    BundleComponentOption,
    BundleQuote,
    ProductImage,
    ProductVideo,
    ReviewFormState,
    ReviewSummary,
    StoreBadge,
    StoreCard,
    StoreReviewPage,
} from '@/Components/storefront/types';
import WishlistButton from '@/Components/storefront/WishlistButton.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    product: {
        id: number;
        type: 'set' | 'bundle';
        name: string;
        short_description: string | null;
        description: string | null;
        images: ProductImage[];
        category: { name: string; url: string } | null;
        video: ProductVideo | null;
    };
    badges: StoreBadge[];
    components: BundleComponentOption[];
    quote: BundleQuote;
    canAddToCart: boolean;
    hasWhatsapp: boolean;
    policies: { shipping?: string; returns?: string };
    related: StoreCard[];
    reviews: StoreReviewPage;
    reviewSummary: ReviewSummary;
    reviewForm: ReviewFormState;
}>();

// The buyer's choice: the variant picked for each component. Every price and limit comes from the
// server in a new `quote`.
const choices = reactive<Record<string, number | undefined>>({ ...props.quote.components });
const quantity = ref(props.quote.quantity);
const updating = ref(false);
const images = computed(() => props.product.images.map((image) => image.url));

let timer: ReturnType<typeof setTimeout> | undefined;

function requote(): void {
    router.reload({
        data: { components: { ...choices }, quantity: quantity.value },
        only: ['quote'],
        preserveUrl: true,
        showProgress: false,
        onFinish: () => (updating.value = false),
    });
}

watch(
    [choices, quantity],
    () => {
        updating.value = true;
        clearTimeout(timer);
        timer = setTimeout(requote, 250);
    },
    { deep: true },
);

// The server caps the quantity by what the chosen pieces allow.
watch(
    () => props.quote.quantity,
    (value) => {
        quantity.value = value;
    },
);

onBeforeUnmount(() => clearTimeout(timer));

const adding = ref(false);
const added = ref(false);
const cartError = ref<string | null>(null);

watch(
    choices,
    () => {
        added.value = false;
        cartError.value = null;
    },
    { deep: true },
);

function addToCart(): void {
    adding.value = true;
    added.value = false;
    cartError.value = null;
    router.post(
        route('store.cart.add'),
        { product_id: props.product.id, quantity: quantity.value, components: { ...choices } },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                added.value = true;
                requote();
            },
            onError: (errors) => {
                cartError.value = errors.components ?? errors.product_id ?? errors.quantity ?? null;
                requote();
            },
            onFinish: () => (adding.value = false),
        },
    );
}
</script>

<template>
    <div class="border-b border-line bg-(--sand-light)">
        <StoreBreadcrumb :title="product.name" :parents="[{ name: t('Shop'), url: route('store.shop') }, ...(product.category ? [product.category] : [])]" :class="container" class="py-4" />
    </div>

    <div :class="container" class="pt-10 pb-28 lg:pb-0">
        <div class="grid gap-8 lg:grid-cols-2 lg:gap-14">
            <ProductGallery :name="product.name" :images="images" :video="product.video" />

            <div class="min-w-0">
                <ProductBadges :badges="badges" :percent-off="quote.price.percent_off" size="page" class="mb-4" />
                <p class="text-sm font-medium text-store-600">{{ product.type === 'set' ? t('Set') : t('Bundle offer') }}</p>
                <div class="mt-1 flex items-start justify-between gap-4">
                    <h1 class="text-3xl leading-tight font-bold sm:text-[2.25rem]">{{ product.name }}</h1>
                    <WishlistButton :product-id="product.id" size="md" class="shrink-0 border border-line" />
                </div>
                <a v-if="reviewSummary.count" href="#reviews" class="mt-2 inline-flex items-center gap-2 text-sm text-(--ink-soft) hover:text-store-600">
                    <StarRating :rating="reviewSummary.average ?? 0" size="sm" />
                    {{ t(':count reviews', { count: reviewSummary.count }) }}
                </a>

                <div class="mt-5 border-b border-line pb-6">
                    <PriceRow :price="quote.price" size="large" />
                    <p class="mt-2 text-sm font-semibold" :class="quote.in_stock ? 'text-[#15803d]' : 'text-(--danger)'">
                        {{ quote.in_stock ? t('In stock') : t('Currently unavailable') }}
                    </p>
                </div>

                <p v-if="product.short_description" class="mt-6 text-[17px] leading-8 text-(--ink-soft)">{{ product.short_description }}</p>

                <section class="mt-8" aria-labelledby="bundle-pieces">
                    <h2 id="bundle-pieces" class="font-bold">{{ product.type === 'set' ? t('In this set') : t('In this offer') }}</h2>
                    <ul class="divide-y divide-line">
                        <BundleComponentPicker v-for="component in components" :key="component.id" v-model="choices[component.id]" :component="component" />
                    </ul>
                </section>

                <div class="mt-4">
                    <QuantityStepper v-model="quantity" :max="quote.max_quantity" />
                </div>

                <ProductOrderBar
                    :total="quote.total_label"
                    :updating="updating"
                    :has-whatsapp="hasWhatsapp"
                    :whatsapp-url="quote.whatsapp_url"
                    :can-add-to-cart="canAddToCart"
                    :ready="quote.ready"
                    :adding="adding"
                    :added="added"
                    :cart-url="route('store.cart')"
                    @add="addToCart"
                />
                <p v-if="cartError" role="alert" class="mt-3 text-sm font-bold text-(--danger)">{{ cartError }}</p>
                <NotifyMeForm v-if="!quote.in_stock" :product-id="product.id" />
                <ProductTrustRow :shipping="policies.shipping" :returns="policies.returns" :has-whatsapp="hasWhatsapp" />

                <ul id="order-problems" class="mt-3 space-y-1 text-sm text-(--ink-soft) empty:hidden" aria-live="polite">
                    <li v-for="problem in quote.problems" :key="problem">{{ problem }}</li>
                </ul>
            </div>
        </div>

        <ProductDetails :details="[]" :description="product.description" />

        <ProductReviews :name="product.name" :reviews="reviews" :summary="reviewSummary" :form="reviewForm" />

        <section v-if="related.length" class="mt-20" aria-labelledby="related-title">
            <SectionHeading id="related-title" :title="t('You may also like')" />
            <ProductGrid :cards="related" class="mt-10" />
        </section>
    </div>
</template>
