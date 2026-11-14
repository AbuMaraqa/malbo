<script setup lang="ts">
import { computed, useId } from 'vue';

import { field, fieldError, fieldLabel } from './styles';

/** A storefront select with its label and error tied together, like StoreField. */
defineProps<{
    label: string;
    options: { value: number | string; label: string }[];
    placeholder: string;
    error?: string;
    required?: boolean;
    autocomplete?: string;
}>();

const model = defineModel<number | string | null>({ default: null });
const id = useId();
const errorId = computed(() => `${id}-error`);
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabel">
            {{ label }}
            <span v-if="required" class="text-(--danger)" aria-hidden="true">*</span>
        </label>
        <select
            :id="id"
            v-model="model"
            :required="required"
            :autocomplete="autocomplete"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="error ? errorId : undefined"
            :class="field"
        >
            <option :value="null" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <p v-if="error" :id="errorId" :class="fieldError">{{ error }}</p>
    </div>
</template>
