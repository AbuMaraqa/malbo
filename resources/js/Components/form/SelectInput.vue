<script setup lang="ts">
import { useId } from 'vue';

import Field from './Field.vue';

export interface Option {
    value: string | number;
    label: string;
}

defineProps<{
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    options: Option[];
    /** Text of the empty choice. Without it a value must be chosen. */
    placeholder?: string;
    disabled?: boolean;
}>();

const model = defineModel<string | number | null>();
const id = useId();
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <select
            :id="id"
            v-model="model"
            :disabled="disabled"
            :aria-invalid="!!error"
            class="h-11 w-full rounded-[10px] border bg-card px-3 text-sm text-gray-800 shadow-theme-xs outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 disabled:bg-gray-100/50 disabled:text-gray-400 dark:bg-gray-900 dark:text-white/90"
            :class="error ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'"
        >
            <option v-if="placeholder !== undefined" :value="null">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </Field>
</template>
