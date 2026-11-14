<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

import type { MerchantForm } from './types';

/** Whether the merchant may order on credit, and up to what its balance and open orders may reach. */
defineProps<{ form: InertiaForm<MerchantForm>; disabled: boolean }>();
</script>

<template>
    <Card :title="t('Credit terms')">
        <div class="grid gap-4 md:grid-cols-2">
            <Toggle
                v-model="form.credit_enabled"
                :label="t('Buys on credit')"
                :hint="t('Checkout offers credit while the balance, the open orders and the new order stay within the limit.')"
                :disabled="disabled"
            />
            <TextInput
                v-model="form.credit_limit"
                type="number"
                min="0"
                step="0.01"
                :label="t('Credit limit')"
                :required="form.credit_enabled"
                :disabled="disabled || !form.credit_enabled"
                :error="form.errors.credit_limit"
            />
        </div>
    </Card>
</template>
