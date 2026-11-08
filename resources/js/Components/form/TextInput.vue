<script setup lang="ts">
import { useId } from 'vue';

import { Input } from '@/tailadmin/ui/input';

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
        <Input
            :id="id"
            :model-value="model ?? ''"
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
            @update:model-value="model = $event"
        />
    </Field>
</template>
