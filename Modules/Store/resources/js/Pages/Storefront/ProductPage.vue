<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import AddonPicker from '@/Components/storefront/AddonPicker.vue';
import CompleteTheLook from '@/Components/storefront/CompleteTheLook.vue';
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
import StarRating from '@/Components/storefront/StarRating.vue';
import SizeGuideDialog from '@/Components/storefront/SizeGuideDialog.vue';
import StoreBreadcrumb from '@/Components/storefront/StoreBreadcrumb.vue';
import SectionHeading from '@/Components/storefront/SectionHeading.vue';
import { container } from '@/Components/storefront/styles';
import type {
    ProductDetail,
    LookItem,
    ProductImage,
    ProductOptions,
    ProductQuote,
    ProductVideo,
    ReviewFormState,
    ReviewSummary,
    SizeGuide,
    StoreBadge,
    StoreCard,
    StorePrice,
    StoreReviewPage,
} from '@/Components/storefront/types';
import VariantPicker from '@/Components/storefront/VariantPicker.vue';
import WishlistButton from '@/Components/storefront/WishlistButton.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    product: {
        id: number;
        name: string;
        short_description: string | null;
        description: string | null;
        images: ProductImage[];
        category: { name: string; url: string } | null;
        details: ProductDetail[];
        size_guide: SizeGuide | null;
        video: ProductVideo | null;
    };
    badges: StoreBadge[];
    options: ProductOptions;
    quote: ProductQuote;
    canAddToCart: boolean;
    hasWhatsapp: boolean;
    policies: { shipping?: string; returns?: string };
    related: StoreCard[];
    /** "Complete the look": staff's picks for this product. */
    look: LookItem[];
    reviews: StoreReviewPage;
    reviewSummary: ReviewSummary;
    reviewForm: ReviewFormState;
}>();

// The buyer's choice. Every price shown comes from the server: a variation's own price, image and
// stock travel with `options`, and the total and the WhatsApp link with a new `quote`.
const selected = reactive<Record<string, number | undefined>>({ ...props.quote.selected });
const addons = reactive<ProductQuote['addons']>({ ...props.quote.addons });
const quantity = ref(props.quote.quantity);
const updating = ref(false);

const variant = computed(() => {
    const wanted = props.options.attributes.map((attribute) => selected[attribute.id]);

    if (props.options.variants.length === 0 || wanted.some((value) => value === undefined)) {
        return null;
    }

    return props.options.variants.find((candidate) => props.options.attributes.every((attribute) => candidate.values[attribute.id] === selected[attribute.id])) ?? null;
});

const hasVariants = computed(() => props.options.variants.length > 0);
const price = computed<StorePrice>(() => variant.value?.price ?? props.quote.price);
const priceFrom = computed(() => (hasVariants.value && !variant.value ? props.quote.price_from : null));
const inStock = computed(() => (hasVariants.value ? (variant.value?.in_stock ?? true) : props.quote.in_stock));
const maxQuantity = computed(() => variant.value?.max_quantity ?? props.quote.max_quantity);

/**
 * The chosen color's images, then the images that show no color, when that color has any; else
 * every image. The chosen variation's own image comes first.
 */
const images = computed(() => {
    const chosen = new Set(Object.values(selected).filter((value): value is number => value !== undefined));
    const colored = props.product.images.filter((image) => image.color !== null && chosen.has(image.color));
    const shown = colored.length ? [...colored, ...props.product.images.filter((image) => image.color === null)] : props.product.images;
    const urls = shown.map((image) => image.url);
    const own = variant.value?.image;

    return own ? [own, ...urls.filter((url) => url !== own)] : urls;
});

/** The product's badges, with the offer badge following the chosen variation's own price. */
const shownBadges = computed<StoreBadge[]>(() => {
    if (!variant.value) return props.badges;

    const rest = props.badges.filter((badge) => badge.key !== 'offer');
    if (!variant.value.on_offer) return rest;

    const offer = props.badges.find((badge) => badge.key === 'offer') ?? { key: 'offer', label: t('Offer'), tone: 'sale' };
    const stockAt = rest.findIndex((badge) => badge.key === 'limited' || badge.key === 'out_of_stock');

    return stockAt === -1 ? [...rest, offer] : [...rest.slice(0, stockAt), offer, ...rest.slice(stockAt)];
});

/** A variation fills in its own details; what it leaves empty is the product's. */
const details = computed(() => variant.value?.details ?? props.product.details);

/** The size guide sits by the attribute staff marked as the size, else by the quantity. */
const sizeAttributeId = computed(() => props.options.attributes.find((attribute) => attribute.size)?.id ?? null);

let timer: ReturnType<typeof setTimeout> | undefined;

function requote(): void {
    router.reload({
        data: { selected: { ...selected }, addons: { ...addons }, quantity: quantity.value },
        only: ['quote'],
        // The choice is for this visit: the address stays the product's own.
        preserveUrl: true,
        showProgress: false,
        onFinish: () => (updating.value = false),
    });
}

watch(
    [selected, addons, quantity],
    () => {
        updating.value = true;
        clearTimeout(timer);
        timer = setTimeout(requote, 250);
    },
    { deep: true },
);

// The server caps the quantity by the order limit and the stock of the chosen variation.
watch(
    () => props.quote.quantity,
    (value) => {
        quantity.value = value;
    },
);

onBeforeUnmount(() => clearTimeout(timer));

// The cart: the chosen variation, or the product itself when it has none. The server checks it again.
const adding = ref(false);
const added = ref(false);
const cartItemId = computed(() => (hasVariants.value ? (variant.value?.id ?? null) : props.product.id));
const readyForCart = computed(() => cartItemId.value !== null && inStock.value && props.quote.problems.length === 0);
// "Notify me when available": the chosen variation (or the product without variations) cannot be ordered now.
const notifyId = computed(() => {
    if (!hasVariants.value) return props.quote.in_stock ? null : props.product.id;

    return variant.value && !variant.value.in_stock ? variant.value.id : null;
});

// Kept here: the reload that prices the choice again brings no errors.
const cartError = ref<string | null>(null);

watch(
    selected,
    () => {
        added.value = false;
        cartError.value = null;
    },
    { deep: true },
);

function addToCart(): void {
    if (cartItemId.value === null) return;

    adding.value = true;
    added.value = false;
    cartError.value = null;
    router.post(
        route('store.cart.add'),
        { product_id: cartItemId.value, quantity: quantity.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                added.value = true;
                // The page came back without the choice in its address: price this choice again.
                requote();
            },
            onError: (errors) => {
                cartError.value = errors.product_id ?? errors.quantity ?? null;
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
                <ProductBadges :badges="shownBadges" :percent-off="price.percent_off" size="page" class="mb-4" />
                <p v-if="product.category" class="text-sm font-medium text-store-600">{{ product.category.name }}</p>
                <div class="mt-1 flex items-start justify-between gap-4">
                    <h1 class="text-3xl leading-tight font-bold sm:text-[2.25rem]">{{ product.name }}</h1>
                    <WishlistButton :product-id="product.id" size="md" class="shrink-0 border border-line" />
                </div>
                <a v-if="reviewSummary.count" href="#reviews" class="mt-2 inline-flex items-center gap-2 text-sm text-(--ink-soft) hover:text-store-600">
                    <StarRating :rating="reviewSummary.average ?? 0" size="sm" />
                    {{ t(':count reviews', { count: reviewSummary.count }) }}
                </a>

                <div class="mt-5 border-b border-line pb-6">
                    <p v-if="priceFrom" class="text-2xl font-bold">{{ t('From :price', { price: priceFrom }) }}</p>
                    <PriceRow v-else :price="price" size="large" />
                    <p class="mt-2 text-sm font-semibold" :class="inStock ? 'text-[#15803d]' : 'text-(--danger)'">
                        {{ inStock ? t('In stock') : t('Currently unavailable') }}
                    </p>
                </div>

                <p v-if="product.short_description" class="mt-6 text-[17px] leading-8 text-(--ink-soft)">{{ product.short_description }}</p>

                <div class="mt-8 space-y-7">
                    <div v-for="attribute in options.attributes" :key="attribute.id">
                        <div v-if="product.size_guide && attribute.id === sizeAttributeId" class="-mb-2 flex justify-end">
                            <SizeGuideDialog :guide="product.size_guide" />
                        </div>
                        <VariantPicker v-model="selected[attribute.id]" :attribute="attribute" />
                    </div>
                    <AddonPicker v-for="addon in options.addons" :key="addon.id" v-model="addons[addon.id]" :addon="addon" />
                    <div>
                        <div v-if="product.size_guide && sizeAttributeId === null" class="-mb-2 flex justify-end">
                            <SizeGuideDialog :guide="product.size_guide" />
                        </div>
                        <QuantityStepper v-model="quantity" :max="maxQuantity" />
                    </div>
                </div>

                <ProductOrderBar
                    :total="quote.total_label"
                    :updating="updating"
                    :has-whatsapp="hasWhatsapp"
                    :whatsapp-url="quote.whatsapp_url"
                    :can-add-to-cart="canAddToCart"
                    :ready="readyForCart"
                    :adding="adding"
                    :added="added"
                    :cart-url="route('store.cart')"
                    @add="addToCart"
                />
                <p v-if="cartError" role="alert" class="mt-3 text-sm font-bold text-(--danger)">{{ cartError }}</p>
                <NotifyMeForm v-if="notifyId !== null" :product-id="notifyId" />
                <ProductTrustRow :shipping="policies.shipping" :returns="policies.returns" :has-whatsapp="hasWhatsapp" />

                <!-- Always present, so screen readers announce changes to it. -->
                <ul id="order-problems" class="mt-3 space-y-1 text-sm text-(--ink-soft) empty:hidden" aria-live="polite">
                    <li v-for="problem in quote.problems" :key="problem">{{ problem }}</li>
                </ul>
                <p v-if="!hasWhatsapp && !canAddToCart" class="mt-4 text-sm text-muted">{{ t('Online ordering is coming soon. Contact the store to order.') }}</p>

            </div>
        </div>

        <ProductDetails :details="details" :description="product.description" />

        <CompleteTheLook v-if="look.length" :items="look" />

        <ProductReviews :name="product.name" :reviews="reviews" :summary="reviewSummary" :form="reviewForm" />

        <section v-if="related.length" class="mt-20" aria-labelledby="related-title">
            <SectionHeading id="related-title" :title="t('You may also like')" />
            <ProductGrid :cards="related" class="mt-10" />
        </section>
    </div>
</template>
