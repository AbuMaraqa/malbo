<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import BreaksCard from './parts/BreaksCard.vue';
import IncomingCard from './parts/IncomingCard.vue';
import TierPricesCard from './parts/TierPricesCard.vue';
import type { Item, PriceForm, Tier } from './parts/types';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    product: { id: number; name: string; sku: string | null; wholesale_price: number | null; by_piece: boolean; edit_url: string };
    items: Item[];
    tiers: Tier[];
    /** item id => tier id => price; an empty PHP object arrives as a list. */
    prices: Record<string, Record<string, number>> | never[];
    breaks: { min_quantity: number; discount_percent: number }[];
}>();

const saved = Array.isArray(props.prices) ? {} : props.prices;

const form = useForm<PriceForm>({
    by_piece: props.product.by_piece,
    prices: Object.fromEntries(
        props.items.map((item) => [String(item.id), Object.fromEntries(props.tiers.map((tier) => [String(tier.id), saved[String(item.id)]?.[String(tier.id)] ?? '']))]),
    ),
    breaks: props.breaks.map((row) => ({ ...row })),
});

function submit(): void {
    form.put(route('wholesale-prices.update', props.product.id), { preserveScroll: true });
}
</script>

<template>
    <PageHeader :title="t('Wholesale prices: :name', { name: product.name })" :parents="[{ title: t('Wholesale prices'), href: route('wholesale-prices.index') }]">
        <template #actions>
            <Button :href="product.edit_url">{{ t('Edit Product') }}</Button>
        </template>
    </PageHeader>

    <form class="grid gap-4" @submit.prevent="submit">
        <Card :title="t('How it is sold')">
            <Toggle
                v-model="form.by_piece"
                :label="t('Sold by the piece')"
                :hint="t('Off: merchants order full size runs only, the same number of every available size of a color.')"
            />
        </Card>

        <TierPricesCard :form="form" :items="items" :tiers="tiers" :base-price="product.wholesale_price" />
        <BreaksCard :form="form" />

        <FormActions :processing="form.processing" :cancel-href="route('wholesale-prices.index')" />
    </form>

    <IncomingCard class="mt-4" :product-id="product.id" :items="items" />
</template>
