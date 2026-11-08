<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';

import AddonPicker from '@/Components/storefront/AddonPicker.vue';
import PriceRow from '@/Components/storefront/PriceRow.vue';
import ProductGallery from '@/Components/storefront/ProductGallery.vue';
import ProductGrid from '@/Components/storefront/ProductGrid.vue';
import ProductOrderBar from '@/Components/storefront/ProductOrderBar.vue';
import ProductTrustRow from '@/Components/storefront/ProductTrustRow.vue';
import QuantityStepper from '@/Components/storefront/QuantityStepper.vue';
import StoreBreadcrumb from '@/Components/storefront/StoreBreadcrumb.vue';
import { glass, prose, sectionTitle } from '@/Components/storefront/styles';
import type { ProductOptions, ProductQuote, StoreCard, StorePrice } from '@/Components/storefront/types';
import VariantPicker from '@/Components/storefront/VariantPicker.vue';
import StorefrontLayout from '@/Layouts/StorefrontLayout.vue';
import { t } from '@/lib/i18n';

defineOptions({ layout: StorefrontLayout });

const props = defineProps<{
    product: {
        id: number;
        name: string;
        short_description: string | null;
        description: string | null;
        images: string[];
        category: { name: string; url: string } | null;
    };
    options: ProductOptions;
    quote: ProductQuote;
    hasWhatsapp: boolean;
    policies: { shipping?: string; returns?: string };
    related: StoreCard[];
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
const images = computed(() => {
    const own = variant.value?.image;

    return own ? [own, ...props.product.images.filter((image) => image !== own)] : props.product.images;
});

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
</script>

<template>
    <div class="mx-auto max-w-310 px-4 pt-8 pb-28 lg:pb-0">
        <StoreBreadcrumb :title="product.name" :parents="product.category ? [product.category] : []" />

        <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12">
            <ProductGallery :name="product.name" :images="images" />

            <div class="min-w-0">
                <h1 class="text-3xl leading-tight font-bold sm:text-4xl">{{ product.name }}</h1>

                <p v-if="priceFrom" class="mt-4 text-2xl font-bold text-store-700">{{ t('From :price', { price: priceFrom }) }}</p>
                <PriceRow v-else :price="price" size="large" class="mt-4">
                    <span v-if="price.discounted" class="rounded-md px-2.5 py-1 text-xs font-bold" :class="glass">{{ t('Sale') }}</span>
                </PriceRow>
                <p v-if="!inStock" class="mt-2 font-bold text-(--danger)">{{ t('Currently unavailable') }}</p>

                <p v-if="product.short_description" class="mt-5 text-[17px] leading-8 text-(--ink-soft)">{{ product.short_description }}</p>

                <div class="mt-8 space-y-7">
                    <VariantPicker v-for="attribute in options.attributes" :key="attribute.id" v-model="selected[attribute.id]" :attribute="attribute" />
                    <AddonPicker v-for="addon in options.addons" :key="addon.id" v-model="addons[addon.id]" :addon="addon" />
                    <QuantityStepper v-model="quantity" :max="maxQuantity" />
                </div>

                <ProductOrderBar :total="quote.total_label" :updating="updating" :has-whatsapp="hasWhatsapp" :whatsapp-url="quote.whatsapp_url" />
                <ProductTrustRow :shipping="policies.shipping" :returns="policies.returns" :has-whatsapp="hasWhatsapp" />

                <!-- Always present, so screen readers announce changes to it. -->
                <ul id="order-problems" class="mt-3 space-y-1 text-sm text-(--ink-soft) empty:hidden" aria-live="polite">
                    <li v-for="problem in quote.problems" :key="problem">{{ problem }}</li>
                </ul>
                <p v-if="!hasWhatsapp" class="mt-4 text-sm text-muted">{{ t('Online ordering is coming soon. Contact the store to order.') }}</p>

                <div v-if="product.description" class="mt-10 border-t border-line pt-8" :class="prose">
                    <h2 class="mb-4 text-xl font-bold">{{ t('Description') }}</h2>
                    <!-- Sanitized on the server (RichText). -->
                    <div v-html="product.description"></div>
                </div>
            </div>
        </div>

        <section v-if="related.length" class="mt-20" aria-labelledby="related-title">
            <h2 id="related-title" :class="sectionTitle">{{ t('You may also like') }}</h2>
            <ProductGrid :cards="related" class="mt-6" />
        </section>
    </div>
</template>
