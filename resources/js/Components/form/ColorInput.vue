<script setup lang="ts">
import { computed, useId } from 'vue';

import Field from './Field.vue';

defineProps<{ label?: string; error?: string; hint?: string; required?: boolean }>();

const model = defineModel<string | null>();
const id = useId();

// The native picker only reads #rrggbb: a short or an alpha value is still kept in the text box.
const swatch = computed(() => (/^#[0-9a-f]{6}$/i.test(model.value ?? '') ? model.value! : '#000000'));
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <div class="flex items-center gap-2">
            <input type="color" :value="swatch" :aria-label="label" class="h-11 w-12 shrink-0 cursor-pointer rounded-[10px] border border-gray-300 bg-card p-1 dark:border-gray-700" @input="model = ($event.target as HTMLInputElement).value" />
            <input
                :id="id"
                v-model="model"
                type="text"
                dir="ltr"
                maxlength="9"
                placeholder="#000000"
                :aria-invalid="!!error"
                class="h-11 w-full min-w-0 rounded-[10px] border bg-card px-3 text-sm text-gray-800 shadow-theme-xs outline-hidden focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 dark:bg-gray-900 dark:text-white/90"
                :class="error ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'"
            />
        </div>
    </Field>
</template>
