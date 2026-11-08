<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import MultiSelect from '@/Components/form/MultiSelect.vue';
import Repeater from '@/Components/form/Repeater.vue';
import TextInput from '@/Components/form/TextInput.vue';
import Toggle from '@/Components/form/Toggle.vue';
import { t } from '@/lib/i18n';

import type { AddonChoice, AddonRow, AllowedOptionRow, ProductForm } from './types';

const props = defineProps<{ form: InertiaForm<ProductForm>; addons: AddonChoice[]; collapsed?: boolean }>();

const errors = computed(() => props.form.errors as Record<string, string | undefined>);

const newAddon = (): AddonRow => ({ addon_id: null, is_required: false, min_selection: 1, max_selection: null, max_quantity_per_option: 1, allowed_options: [] });
const newOption = (): AllowedOptionRow => ({ addon_option_id: null, price: null, is_default: false });

const addonOf = (row: AddonRow): AddonChoice | undefined => props.addons.find((addon) => String(addon.id) === String(row.addon_id));

/** An addon appears once: a row offers the ones no other row has picked. */
function addonChoices(row: AddonRow): { value: number; label: string }[] {
    const taken = props.form.addons.filter((other) => other !== row).map((other) => String(other.addon_id));

    return props.addons.filter((addon) => !taken.includes(String(addon.id))).map((addon) => ({ value: addon.id, label: addon.name }));
}

/** The same for an option within its addon; the label shows its default price. */
function optionChoices(addonRow: AddonRow, row: AllowedOptionRow): { value: number; label: string }[] {
    const taken = addonRow.allowed_options.filter((other) => other !== row).map((other) => String(other.addon_option_id));

    return (addonOf(addonRow)?.options ?? [])
        .filter((option) => !taken.includes(String(option.id)))
        .map((option) => ({ value: option.id, label: `${option.name} (${t('Default:')} ${option.price})` }));
}

/** Options of another addon have no meaning once the group changes. */
function changeAddon(row: AddonRow, value: unknown): void {
    if (String(row.addon_id) !== String(value)) row.allowed_options = [];
    row.addon_id = value as AddonRow['addon_id'];
}

</script>

<template>
    <Repeater
        v-model="form.addons"
        name="addons"
        :errors="errors"
        :new-item="newAddon"
        :label="t('Link Addons to this Product')"
        :add-label="t('Add')"
        :empty-text="t('No addons linked yet')"
        :item-label="(row) => addonOf(row)?.name ?? t('New Option')"
        :reorderable="false"
        collapsible
        :collapsed="collapsed"
    >
        <template #item="{ item, index, error }">
            <div class="grid gap-4 md:grid-cols-6">
                <MultiSelect :model-value="item.addon_id" class="md:col-span-2" single :label="t('Addon Group')" required :options="addonChoices(item)" :error="error('addon_id')" @update:model-value="changeAddon(item, $event)" />
                <TextInput v-model="item.min_selection" :label="t('Min Selection')" type="number" min="0" :error="error('min_selection')" />
                <TextInput v-model="item.max_selection" :label="t('Max Selection')" type="number" min="1" :placeholder="t('Leave empty for no limit')" :error="error('max_selection')" />
                <TextInput v-model="item.max_quantity_per_option" :label="t('Max Qty Per Option')" type="number" min="1" :error="error('max_quantity_per_option')" />
                <Toggle v-model="item.is_required" class="self-center" :label="t('Is Required?')" />

                <div v-if="item.addon_id" class="md:col-span-6">
                    <Repeater
                        v-model="item.allowed_options"
                        :name="`addons.${index}.allowed_options`"
                        :errors="errors"
                        :new-item="newOption"
                        :label="t('Allowed Options & Custom Prices')"
                        :add-label="t('Add Option')"
                        :empty-text="t('Add the allowed options. Leave the price empty to use the default price.')"
                        :reorderable="false"
                    >
                        <template #item="{ item: option, error: optionError }">
                            <div class="grid gap-3 md:grid-cols-3">
                                <MultiSelect v-model="option.addon_option_id" single :label="t('Option')" required :options="optionChoices(item, option)" :error="optionError('addon_option_id')" />
                                <TextInput v-model="option.price" :label="t('Override Price')" type="number" step="0.01" min="0" :placeholder="t('Use default price')" :error="optionError('price')" />
                                <Toggle v-model="option.is_default" class="self-center" :label="t('Is Default')" />
                            </div>
                        </template>
                    </Repeater>
                </div>
            </div>
        </template>
    </Repeater>
</template>
