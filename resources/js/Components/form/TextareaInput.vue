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
        rows?: number;
        maxlength?: number;
        disabled?: boolean;
    }>(),
    { rows: 3 },
);

const model = defineModel<string | null>();
const id = useId();
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <textarea
            :id="id"
            :value="model ?? ''"
            :rows="rows"
            :maxlength="maxlength"
            :disabled="disabled"
            :aria-invalid="!!error"
            :class="[controlClasses, 'block py-2.5 leading-6']"
            @input="model = ($event.target as HTMLTextAreaElement).value"
        />
    </Field>
</template>
