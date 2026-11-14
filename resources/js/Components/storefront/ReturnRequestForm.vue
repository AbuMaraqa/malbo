<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import StoreField from './StoreField.vue';
import StoreSelect from './StoreSelect.vue';
import { btnBrand, field, fieldError, fieldLabel } from './styles';
import type { ReturnForm } from './types';

/**
 * Asks to return or exchange items of a delivered order: how many of each line, why, and for an
 * exchange what to take instead. The server checks it all again.
 */
const props = defineProps<{ orderNumber: string; form: ReturnForm }>();

const request = useForm({
    type: props.form.types[0]?.value ?? 'return',
    reason: null as string | null,
    customer_note: '',
    lines: props.form.lines.map((line) => ({ order_item_id: line.order_item_id, quantity: 0, exchange_product_id: null as number | null })),
});

const exchanging = computed(() => request.type === 'exchange');
/** Errors about the order itself, or the rate limit. */
const otherErrors = computed(() => request.errors as Record<string, string | undefined>);
const chosen = computed(() => request.lines.some((line) => line.quantity > 0));

/** Errors of a line, by its place in the list the server saw (lines at 0 are left out there). */
function lineError(index: number, key: string): string | undefined {
    const sent = request.lines.slice(0, index + 1).filter((line) => line.quantity > 0).length - 1;
    const errors = request.errors as Record<string, string>;

    return request.lines[index].quantity > 0 ? errors[`lines.${sent}.${key}`] : undefined;
}

function submit(): void {
    request
        .transform((data) => ({ ...data, lines: data.lines.map((line) => ({ ...line, exchange_product_id: exchanging.value ? line.exchange_product_id : null })) }))
        .post(route('store.account.orders.returns.store', { number: props.orderNumber }), { preserveScroll: true, onSuccess: () => request.reset() });
}
</script>

<template>
    <form class="grid gap-6" novalidate @submit.prevent="submit">
        <fieldset>
            <legend :class="fieldLabel">{{ t('What would you like to do?') }}</legend>
            <div class="mt-1 flex flex-wrap gap-3">
                <label v-for="type in form.types" :key="type.value" class="flex cursor-pointer items-center gap-2 rounded-md border border-line px-4 py-3 has-checked:border-store">
                    <input v-model="request.type" type="radio" name="type" :value="type.value" class="size-4 accent-(--color-store)" />
                    <span class="font-bold">{{ type.label }}</span>
                </label>
            </div>
        </fieldset>

        <fieldset class="grid gap-4">
            <legend :class="fieldLabel">{{ t('Items to send back') }}</legend>
            <div v-for="(line, index) in form.lines" :key="line.order_item_id" class="grid gap-3 rounded-md border border-line p-4 sm:grid-cols-[1fr_8rem]">
                <div>
                    <p class="font-bold">{{ line.name }}</p>
                    <p v-if="line.options.length" class="text-sm text-(--ink-soft)">{{ line.options.map((option) => `${option.name}: ${option.value}`).join(' · ') }}</p>
                </div>
                <label class="grid gap-1 text-sm">
                    <span class="font-bold">{{ t('Quantity') }}</span>
                    <input
                        v-model.number="request.lines[index].quantity"
                        type="number"
                        min="0"
                        :max="line.max"
                        inputmode="numeric"
                        :class="field"
                        :aria-invalid="lineError(index, 'quantity') ? true : undefined"
                    />
                </label>
                <p v-if="lineError(index, 'quantity')" :class="fieldError" class="sm:col-span-2">{{ lineError(index, 'quantity') }}</p>
                <div v-if="exchanging && request.lines[index].quantity > 0" class="sm:col-span-2">
                    <StoreSelect
                        v-if="line.exchange_options.length"
                        v-model="request.lines[index].exchange_product_id"
                        :label="t('Exchange for')"
                        :placeholder="t('Choose a size or color')"
                        :options="line.exchange_options"
                        required
                        :error="lineError(index, 'exchange_product_id')"
                    />
                    <p v-else :class="fieldError">{{ t('Nothing is available to exchange this item for right now.') }}</p>
                </div>
            </div>
            <p v-if="request.errors.lines" :class="fieldError">{{ request.errors.lines }}</p>
        </fieldset>

        <StoreSelect v-model="request.reason" :label="t('Reason')" :placeholder="t('Choose a reason')" :options="form.reasons" required :error="request.errors.reason" />
        <StoreField v-model="request.customer_note" :label="t('Notes')" :hint="t('optional')" :rows="3" :maxlength="1000" :error="request.errors.customer_note" />

        <p v-if="otherErrors.order || otherErrors.returns" role="alert" :class="fieldError">{{ otherErrors.order ?? otherErrors.returns }}</p>

        <div class="flex flex-wrap items-center gap-4">
            <button type="submit" :class="btnBrand" :disabled="request.processing || !chosen">{{ t('Send the request') }}</button>
            <p class="text-sm text-(--ink-soft)">{{ t('You can ask until :date.', { date: form.window_ends }) }}</p>
        </div>
    </form>
</template>
