<script setup lang="ts">
import { useId } from 'vue';

import { controlClasses } from './controlClasses';
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
            :class="[controlClasses, 'h-10 pe-8']"
        >
            <option v-if="placeholder !== undefined" :value="null">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
    </Field>
</template>
