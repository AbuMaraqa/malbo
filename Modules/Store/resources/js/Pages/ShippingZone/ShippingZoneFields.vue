<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import Card from '@/Components/ui/Card.vue';
import { t } from '@/lib/i18n';

export interface ShippingZoneForm {
    name: string;
    fee: number | string;
    eta_min_days: number | string;
    eta_max_days: number | string;
    free_shipping_threshold: number | string | null;
    is_active: boolean;
    sort_order: number | string;
    cities: (number | string)[];
}

defineProps<{ form: InertiaForm<ShippingZoneForm>; cities: Option[] }>();

function firstError(errors: Record<string, string | undefined>, prefix: string): string | undefined {
    const key = Object.keys(errors).find((item) => item === prefix || item.startsWith(`${prefix}.`));

    return key ? errors[key] : undefined;
}
</script>

<template>
    <div class="grid items-start gap-4 lg:grid-cols-3">
        <div class="grid gap-4 lg:col-span-2">
            <Card>
                <div class="grid gap-4 md:grid-cols-2">
                    <TextInput v-model="form.name" class="md:col-span-2" :label="t('Name')" required :error="form.errors.name" />
                    <TextInput v-model="form.fee" type="number" min="0" step="0.01" :label="t('Delivery fee')" required :error="form.errors.fee" />
                    <TextInput
                        v-model="form.free_shipping_threshold"
                        type="number"
                        min="0"
                        step="0.01"
                        :label="t('Free delivery from')"
                        :hint="t('Empty: the store default applies')"
                        :error="form.errors.free_shipping_threshold"
                    />
                    <TextInput v-model="form.eta_min_days" type="number" min="0" :label="t('Fastest delivery (days)')" required :error="form.errors.eta_min_days" />
                    <TextInput v-model="form.eta_max_days" type="number" min="0" :label="t('Slowest delivery (days)')" required :error="form.errors.eta_max_days" />
                </div>
            </Card>
            <Card :title="t('Cities')">
                <MultiSelect
                    v-model="form.cities"
                    :options="cities"
                    :label="t('Cities this zone delivers to')"
                    :placeholder="t('Search cities')"
                    :hint="t('A city can belong to one active zone only.')"
                    :error="firstError(form.errors as Record<string, string | undefined>, 'cities')"
                />
            </Card>
        </div>
        <Card>
            <div class="grid gap-4">
                <Toggle v-model="form.is_active" :label="t('Active')" :hint="t('An inactive zone delivers nowhere.')" />
                <TextInput v-model="form.sort_order" type="number" min="0" :label="t('Sort Order')" :error="form.errors.sort_order" />
            </div>
        </Card>
    </div>
</template>
