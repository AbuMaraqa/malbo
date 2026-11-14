<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import TextareaInput from '@/Components/form/TextareaInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Button from '@/Components/ui/Button.vue';
import Modal from '@/Components/ui/Modal.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

/** A correction to the account: + the merchant owes more, − less. The reason is required. */
const props = defineProps<{ merchantId: number; today: string }>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm<{ amount: string; entry_date: string; reference: string; note: string }>({ amount: '', entry_date: props.today, reference: '', note: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.post(route('merchants.adjustments.store', props.merchantId), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Modal v-model:open="open" :title="t('Record an adjustment')" :description="t('A positive amount raises what the merchant owes; a negative one lowers it.')">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <TextInput v-model="form.amount" type="number" step="0.01" :label="t('Amount')" required :error="form.errors.amount" />
                <TextInput v-model="form.entry_date" type="date" :label="t('Date')" required :error="form.errors.entry_date" />
            </div>
            <TextInput v-model="form.reference" :label="t('Reference')" :hint="t('The merchant sees the reference on their statement.')" dir="ltr" :maxlength="100" :error="form.errors.reference" />
            <TextareaInput v-model="form.note" :label="t('Reason')" :hint="t('The merchant sees this reason on their statement.')" required :rows="3" :maxlength="2000" :error="form.errors.note" />
        </form>
        <template #footer>
            <Button @click="open = false">{{ t('Cancel') }}</Button>
            <Button variant="primary" :loading="form.processing" @click="submit">{{ t('Save') }}</Button>
        </template>
    </Modal>
</template>
