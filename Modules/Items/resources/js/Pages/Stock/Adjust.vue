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

/** A correction by hand: the stock moves by the change typed, with the reason in the ledger. */
const props = defineProps<{ selected: StockProduct | null }>();

const form = useForm<{ product_id: number | string | null; delta: number | string; reason: string }>({
    product_id: props.selected?.value ?? null,
    delta: '',
    reason: '',
});

function submit(): void {
    if (form.processing) return;

    form.post(route('stock.adjust.store'), { preserveScroll: true, preserveState: 'errors' });
}
</script>

<template>
    <PageHeader :title="t('Stock adjustment')" :description="t('Moves the stock up or down by a number, for a count, damage or loss.')" :parents="[{ title: t('Stock movements'), href: route('stock.movements') }]" />
    <form @submit.prevent="submit">
        <div class="grid max-w-3xl gap-4 md:grid-cols-2">
            <StockProductField v-model="form.product_id" class="md:col-span-2" :selected="selected" :error="form.errors.product_id" incoming-hint />
            <TextInput v-model="form.delta" :label="t('Change')" type="number" step="1" required dir="ltr" :hint="t('Negative takes units away: -2 for two damaged pieces.')" :error="form.errors.delta" />
            <TextInput v-model="form.reason" :label="t('Reason')" :maxlength="255" required :error="form.errors.reason" />
        </div>
        <FormActions :processing="form.processing" :cancel-href="route('stock.movements')" :submit-label="t('Save adjustment')" />
    </form>
</template>
