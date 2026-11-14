<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import type { Option } from '@/Components/form/SelectInput.vue';
import SelectInput from '@/Components/form/SelectInput.vue';
import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** Records money the merchant paid, with an optional receipt (kept private). */
const props = defineProps<{ merchantId: number; methods: Option[]; today: string }>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ amount: string; method: string; entry_date: string; reference: string; note: string; receipt: File | null }>({
    amount: '',
    method: 'bank_transfer',
    entry_date: props.today,
    reference: '',
    note: '',
    receipt: null,
});

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function pick(event: Event): void {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    form.post(route('merchants.payments.store', props.merchantId), { preserveScroll: true, forceFormData: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Modal v-model:open="open" :title="t('Record a payment')" :description="t('Money the merchant paid: it lowers the balance.')">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput v-model="form.amount" type="number" min="0.01" step="0.01" :label="t('Amount')" required :error="form.errors.amount" />
                <SelectInput v-model="form.method" :label="t('Payment method')" :options="methods" required :error="form.errors.method" />
                <TextInput v-model="form.entry_date" type="date" :label="t('Date')" required :error="form.errors.entry_date" />
                <TextInput v-model="form.reference" :label="t('Reference')" :hint="t('The merchant sees the reference on their statement.')" dir="ltr" :maxlength="100" :error="form.errors.reference" />
            </div>
            <TextareaInput v-model="form.note" :label="t('Note')" :hint="t('For staff only: the merchant does not see it.')" :rows="2" :maxlength="2000" :error="form.errors.note" />
            <div>
                <label class="mb-1.5 block text-sm font-medium text-ink" for="payment-receipt">{{ t('Receipt') }}</label>
                <input id="payment-receipt" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="block w-full text-sm text-muted" @change="pick" />
                <p class="mt-1 text-xs text-muted">{{ t('A PDF or an image, up to 5 MB. Only staff can open it.') }}</p>
                <p v-if="form.errors.receipt" class="mt-1 text-sm text-error-600 dark:text-error-400">{{ form.errors.receipt }}</p>
            </div>
        </form>
        <template #footer>
            <Button @click="open = false">{{ t('Cancel') }}</Button>
            <Button variant="primary" :loading="form.processing" @click="submit">{{ t('Save') }}</Button>
        </template>
    </Modal>
</template>
