<script setup lang="ts">
import { useId } from 'vue';

import { Textarea } from '@/tailadmin/ui/textarea';

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
        <Textarea
            :id="id"
            :model-value="model ?? ''"
            :rows="rows"
            :maxlength="maxlength"
            :disabled="disabled"
            :aria-invalid="!!error"
            @update:model-value="model = String($event)"
        />
    </Field>
</template>
