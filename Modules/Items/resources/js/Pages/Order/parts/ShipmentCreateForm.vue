<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** Hands the order to a courier: a new shipment, pending until the courier picks it up. */
const props = defineProps<{ orderId: number; couriers: Option[] }>();

const form = useForm<{ order_id: number; courier: string; tracking_number: string; cost: string | number; notes: string }>({
    order_id: props.orderId,
    courier: String(props.couriers[0]?.value ?? ''),
    tracking_number: '',
    cost: '',
    notes: '',
});

function submit(): void {
    if (form.processing) return;

    // The page keeps its state (unsaved order edits stay); Edit.vue follows the order's new status.
    form.post(route('shipments.store'), { preserveScroll: true });
}
</script>

<template>
    <form class="grid gap-4 md:grid-cols-2" @submit.prevent="submit">
        <p v-if="form.errors.order_id" role="alert" class="rounded-control border border-error-500/40 bg-error-50 px-4 py-3 text-sm text-error-700 md:col-span-2 dark:bg-error-500/15 dark:text-error-400">
            {{ form.errors.order_id }}
        </p>
        <SelectInput v-model="form.courier" :label="t('Courier')" required :options="couriers" :error="form.errors.courier" />
        <TextInput v-model="form.tracking_number" :label="t('Tracking number')" dir="ltr" :maxlength="100" :error="form.errors.tracking_number" />
        <TextInput v-model="form.cost" :label="t('Shipping cost')" type="number" min="0" step="0.01" :error="form.errors.cost" :hint="t('What MALBO pays the courier.')" />
        <TextareaInput v-model="form.notes" :label="t('Notes')" :rows="2" :maxlength="2000" :error="form.errors.notes" />
        <div class="md:col-span-2">
            <Button type="submit" variant="primary" :loading="form.processing">{{ t('Create shipment') }}</Button>
        </div>
    </form>
</template>
