<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

import SelectInput, { type Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/**
 * Moves the shipment to its next status, with a note. Picked up and in transit put the order with
 * the courier, delivered delivers it, returned to sender returns it (and its stock).
 */
const props = defineProps<{ shipmentId: number; transitions: Option[] }>();

const form = useForm<{ status: string; note: string }>({ status: String(props.transitions[0]?.value ?? ''), note: '' });

function submit(): void {
    if (form.processing) return;

    // The page keeps its state (unsaved order edits stay); Edit.vue follows the order's new status.
    form.put(route('shipments.status', props.shipmentId), { preserveScroll: true });
}
</script>

<template>
    <form class="flex flex-wrap items-end gap-3" @submit.prevent="submit">
        <SelectInput v-model="form.status" class="w-56" :label="t('Move to')" required :options="transitions" :error="form.errors.status" />
        <TextInput v-model="form.note" class="min-w-56 flex-1" :label="t('Note')" :maxlength="1000" :error="form.errors.note" />
        <Button type="submit" variant="primary" :loading="form.processing">{{ t('Update shipment') }}</Button>
    </form>
</template>
