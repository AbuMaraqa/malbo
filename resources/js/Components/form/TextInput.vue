<script setup lang="ts">
import { useId } from 'vue';

import { controlClasses } from './controlClasses';
import Field from './Field.vue';

withDefaults(
    defineProps<{
        label?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        type?: string;
        placeholder?: string;
        disabled?: boolean;
        autocomplete?: string;
        step?: string;
        min?: string | number;
        max?: string | number;
        maxlength?: number;
        dir?: 'ltr' | 'rtl' | 'auto';
    }>(),
    { type: 'text' },
);

const model = defineModel<string | number | null>();
const id = useId();
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <input
            :id="id"
            :value="model ?? ''"
            :type="type"
            :placeholder="placeholder"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :step="step"
            :min="min"
            :max="max"
            :maxlength="maxlength"
            :dir="dir"
            :aria-invalid="!!error"
            :class="[controlClasses, 'h-10']"
            @input="model = ($event.target as HTMLInputElement).value"
        />
    </Field>
</template>
