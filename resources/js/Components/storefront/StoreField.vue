<script setup lang="ts">
import { computed, useId } from 'vue';

import { field, fieldError, fieldLabel } from './styles';

/**
 * A storefront form field. Not the admin's `form/` inputs: this one has the store's own look.
 * The label, the error and `aria-describedby` stay tied together.
 */
const props = withDefaults(
    defineProps<{
        label: string;
        error?: string;
        hint?: string;
        required?: boolean;
        type?: string;
        autocomplete?: string;
        inputmode?: 'text' | 'tel' | 'email';
        maxlength?: number;
        dir?: 'ltr' | 'rtl';
        rows?: number;
    }>(),
    { type: 'text' },
);

const model = defineModel<string>({ default: '' });
const id = useId();
const errorId = computed(() => `${id}-error`);
</script>

<template>
    <div>
        <label :for="id" :class="fieldLabel">
            {{ label }}
            <span v-if="required" class="text-(--danger)" aria-hidden="true">*</span>
            <span v-if="hint" class="font-normal text-muted">({{ hint }})</span>
        </label>
        <textarea
            v-if="rows"
            :id="id"
            v-model="model"
            :rows="rows"
            :required="required"
            :maxlength="maxlength"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="error ? errorId : undefined"
            class="h-auto py-3 leading-7"
            :class="field"
        ></textarea>
        <input
            v-else
            :id="id"
            v-model="model"
            :type="type"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :dir="dir"
            :required="required"
            :maxlength="maxlength"
            :aria-invalid="error ? true : undefined"
            :aria-describedby="error ? errorId : undefined"
            class="text-start"
            :class="field"
        />
        <p v-if="error" :id="errorId" :class="fieldError">{{ error }}</p>
    </div>
</template>
