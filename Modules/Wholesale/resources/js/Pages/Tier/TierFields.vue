<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface TierForm {
    name: string;
    discount_percent: number | string;
    sort_order: number | string;
    is_default: boolean;
}

defineProps<{ form: InertiaForm<TierForm> }>();
</script>

<template>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <Card class="lg:col-span-2">
            <div class="grid gap-4 md:grid-cols-2">
                <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
                <TextInput
                    v-model="form.discount_percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    :label="t('Discount off the wholesale price (%)')"
                    :hint="t('0: the wholesale price as it is')"
                    required
                    :error="form.errors.discount_percent"
                />
                <TextInput v-model="form.sort_order" type="number" min="0" :label="t('Sort Order')" :error="form.errors.sort_order" />
            </div>
        </Card>
        <Card>
            <Toggle v-model="form.is_default" :label="t('Default tier')" :hint="t('Merchants without a tier get this one.')" />
        </Card>
    </div>
</template>
