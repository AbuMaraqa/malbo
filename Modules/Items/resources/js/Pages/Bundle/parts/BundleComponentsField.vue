<script setup lang="ts">
/**
 * The pieces of a set or bundle, in order: a product (the buyer chooses its size and colour on the
 * storefront) or one fixed variant, and how many of it one bundle holds.
 */
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import RemoteSelect from '@/Components/form/RemoteSelect.vue';
import Repeater from '@/Components/form/Repeater.vue';
import type { Option } from '@/Components/form/SelectInput.vue';
import TextInput from '@/Components/form/TextInput.vue';
import { t } from '@/lib/i18n';
import { route } from '@/lib/route';

import type { BundleComponentRow, BundleForm } from './types';

const props = defineProps<{ form: InertiaForm<BundleForm>; selected?: Option[] }>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);
const newItem = (): BundleComponentRow => ({ product_id: null, quantity: 1 });
</script>

<template>
    <Repeater
        v-model="form.components"
        name="components"
        :errors="errors"
        :new-item="newItem"
        :max="10"
        :add-label="t('Add component')"
        :empty-text="t('No components yet')"
    >
        <template #item="{ item, error }">
            <div class="grid gap-3 md:grid-cols-4">
                <RemoteSelect
                    v-model="item.product_id"
                    class="md:col-span-3"
                    :url="route('bundles.lookup')"
                    :initial="selected"
                    :label="t('Product')"
                    :hint="t('A product with sizes lets the buyer choose; a variant is always that one.')"
                    required
                    :error="error('product_id')"
                />
                <TextInput v-model="item.quantity" :label="t('Quantity')" type="number" min="1" max="20" required :error="error('quantity')" />
            </div>
        </template>
    </Repeater>
</template>
