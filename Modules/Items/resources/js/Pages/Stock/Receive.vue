<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import FormActions from '@/Components/form/FormActions.vue';
import TextInput from '@/Components/form/TextInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import StockProductField, { type StockProduct } from './StockProductField.vue';

defineOptions({ layout: AdminLayout });

/** Goods that arrived: added to whatever the stock is now, never set as a level. */
const props = defineProps<{ selected: StockProduct | null }>();

const form = useForm<{ product_id: number | string | null; quantity: number | string; note: string }>({
    product_id: props.selected?.value ?? null,
    quantity: '',
    note: '',
});

function submit(): void {
    if (form.processing) return;

    form.post(route('stock.receive.store'), { preserveScroll: true, preserveState: 'errors' });
}
</script>

<template>
    <PageHeader :title="t('Receive goods')" :description="t('Adds the units that arrived to the current stock.')" :parents="[{ title: t('Stock movements'), href: route('stock.movements') }]" />
    <form @submit.prevent="submit">
        <div class="grid max-w-3xl gap-4 md:grid-cols-2">
            <StockProductField v-model="form.product_id" class="md:col-span-2" :selected="selected" :error="form.errors.product_id" />
            <TextInput v-model="form.quantity" :label="t('Quantity received')" type="number" min="1" step="1" required :error="form.errors.quantity" />
            <TextInput v-model="form.note" :label="t('Note')" :maxlength="255" :hint="t('A delivery note or supplier, for the ledger.')" :error="form.errors.note" />
        </div>
        <FormActions :processing="form.processing" :cancel-href="route('stock.movements')" :submit-label="t('Receive goods')" />
    </form>
</template>
