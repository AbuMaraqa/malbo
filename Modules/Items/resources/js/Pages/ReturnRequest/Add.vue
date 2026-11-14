<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import FormActions from '@/Components/form/FormActions.vue';
import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import ReturnLinesFields, { type LineInput, type ReturnableLine } from './ReturnLinesFields.vue';

defineOptions({ layout: AdminLayout });

const props = defineProps<{
    orderNumber: string;
    notFound: boolean;
    order: { id: number; number: string; status: string; customer: string | null; lines: ReturnableLine[] } | null;
    /** Why no request can be made on this order now (not delivered, an open request...). */
    blocked: string | null;
    types: Option[];
    reasons: Option[];
}>();

const search = ref(props.orderNumber);

const form = useForm({
    order_id: props.order?.id ?? null,
    type: 'return',
    reason: null as string | null,
    customer_note: '',
    staff_note: '',
    lines: (props.order?.lines ?? []).map((line): LineInput => ({ order_item_id: line.order_item_id, quantity: 0, exchange_product_id: null })),
});

const returnable = computed(() => (props.order?.lines ?? []).filter((line) => line.max > 0));
/** Errors about the order itself (delivered, an open request...). */
const otherErrors = computed(() => form.errors as Record<string, string | undefined>);

/** The server's `lines.N.*` errors, keyed by the line's place on the whole list. */
const lineErrors = computed(() => {
    const errors: Record<string, string> = {};
    let sent = -1;

    form.lines.forEach((line, index) => {
        if (Number(line.quantity) <= 0) return;
        sent++;

        for (const field of ['quantity', 'exchange_product_id', 'order_item_id']) {
            const message = (form.errors as Record<string, string>)[`lines.${sent}.${field}`];
            if (message) errors[`line.${index}.${field === 'order_item_id' ? 'quantity' : field}`] = message;
        }
    });

    return errors;
});

function find(): void {
    router.get(route('return-requests.add'), { order: search.value.trim() || undefined }, { preserveState: false });
}

function submit(): void {
    if (form.processing) return;

    form.transform((data) => ({
        ...data,
        lines: data.lines.map((line) => ({ ...line, exchange_product_id: data.type === 'exchange' ? line.exchange_product_id : null })),
    })).post(route('return-requests.store'));
}
</script>

<template>
    <PageHeader :title="t('Add return request')" :parents="[{ title: t('Return requests'), href: route('return-requests.index') }]" />

    <Card :title="t('Order')" :description="t('Find a delivered order by its number.')">
        <form class="flex flex-wrap items-end gap-3" @submit.prevent="find">
            <TextInput v-model="search" class="w-72" :label="t('Order Number')" dir="ltr" :error="notFound ? t('No order has this number.') : undefined" />
            <Button type="submit" variant="secondary">{{ t('Find') }}</Button>
        </form>
        <p v-if="order" class="mt-4 text-sm">
            <span class="font-medium" dir="ltr">{{ order.number }}</span> · {{ order.status }}<template v-if="order.customer"> · {{ order.customer }}</template>
        </p>
        <p v-if="blocked" role="alert" class="mt-3 text-sm text-error-600 dark:text-error-400">{{ blocked }}</p>
    </Card>

    <form v-if="order && !blocked" class="mt-4 grid gap-4" @submit.prevent="submit">
        <Card :title="t('Request')">
            <div class="grid gap-4 md:grid-cols-2">
                <SelectInput v-model="form.type" :label="t('Type')" :options="types" required :error="form.errors.type" />
                <SelectInput v-model="form.reason" :label="t('Reason')" :options="reasons" :placeholder="t('Choose a reason')" required :error="form.errors.reason" />
                <TextareaInput v-model="form.customer_note" :label="t('Customer note')" :rows="3" :maxlength="1000" :error="form.errors.customer_note" />
                <TextareaInput v-model="form.staff_note" :label="t('Staff note')" :hint="t('Only staff see it.')" :rows="3" :maxlength="2000" :error="form.errors.staff_note" />
            </div>
            <p v-if="otherErrors.order || form.errors.lines" role="alert" class="mt-3 text-sm text-error-600 dark:text-error-400">{{ otherErrors.order ?? form.errors.lines }}</p>
        </Card>

        <ReturnLinesFields v-model="form.lines" :lines="order.lines" :exchanging="form.type === 'exchange'" :errors="lineErrors" />
        <p v-if="!returnable.length" class="text-sm text-muted">{{ t('Nothing is left to send back on this order.') }}</p>

        <FormActions :processing="form.processing" :cancel-href="route('return-requests.index')" />
    </form>
</template>
