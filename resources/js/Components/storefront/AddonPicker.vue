<script setup lang="ts">
import { computed } from 'vue';

import { t } from '@/lib/i18n';

import type { ProductAddon } from './types';

/** One addon: radio buttons, or checkboxes when several may be chosen. */
const props = defineProps<{ addon: ProductAddon }>();

const model = defineModel<number | string | (number | string)[] | null>();
const groupName = computed(() => `addon-${props.addon.id}`);

const list = computed<(number | string)[]>({
    get: () => (Array.isArray(model.value) ? model.value : []),
    set: (value) => {
        model.value = value;
    },
});
</script>

<template>
    <fieldset>
        <legend class="mb-3 font-bold">
            {{ addon.name }}
            <span class="text-sm font-normal text-muted">
                ({{ addon.required ? t('required') : t('optional') }})
                <template v-if="addon.multiple && addon.max > 1"> — {{ t('up to :max', { max: addon.max }) }}</template>
            </span>
        </legend>
        <div class="divide-y divide-line rounded-lg border border-line bg-white">
            <label v-for="option in addon.options" :key="option.id" class="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2">
                <input v-if="addon.multiple" v-model="list" type="checkbox" :value="option.id" class="size-5 accent-store" />
                <input v-else v-model="model" type="radio" :name="groupName" :value="option.id" class="size-5 accent-store" />
                <span class="flex-1">{{ option.name }}</span>
                <span v-if="option.price_label" class="text-sm text-(--ink-soft)">+{{ option.price_label }}</span>
            </label>
        </div>
    </fieldset>
</template>
