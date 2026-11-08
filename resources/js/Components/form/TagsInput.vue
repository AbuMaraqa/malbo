<script setup lang="ts">
/**
 * Free text tags with suggestions: Enter or a comma adds what was typed, whether or not it is a
 * suggestion. The value is a list of strings.
 *
 *   <TagsInput v-model="form.tags" :suggestions="tagNames" :label="t('Tags')" :error="form.errors.tags" />
 */
import { computed, ref, useId } from 'vue';

import { X } from '@/lib/icons';
import { t } from '@/lib/i18n';

import Field from './Field.vue';

const props = withDefaults(
    defineProps<{
        label?: string;
        error?: string;
        hint?: string;
        placeholder?: string;
        suggestions?: string[];
    }>(),
    { suggestions: () => [] },
);

const model = defineModel<string[]>({ required: true });
const id = useId();
const text = ref('');
const open = ref(false);

const matching = computed(() => {
    const term = text.value.trim().toLowerCase();

    return props.suggestions
        .filter((suggestion) => !model.value.includes(suggestion) && (!term || suggestion.toLowerCase().includes(term)))
        .slice(0, 8);
});

function add(value: string): void {
    const tag = value.trim();

    if (tag && !model.value.includes(tag)) {
        model.value = [...model.value, tag];
    }

    text.value = '';
}

function remove(tag: string): void {
    model.value = model.value.filter((item) => item !== tag);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ',' || event.key === '،') {
        event.preventDefault();
        add(text.value);
    } else if (event.key === 'Backspace' && !text.value && model.value.length) {
        model.value = model.value.slice(0, -1);
    }
}

function onBlur(): void {
    // After a click on a suggestion, which the mousedown below already added.
    add(text.value);
    open.value = false;
}
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint">
        <div class="relative">
            <div class="flex min-h-11 w-full flex-wrap items-center gap-1 rounded-[10px] border bg-card px-2 py-1 focus-within:border-brand-300" :class="error ? 'border-red-500' : 'border-gray-300 dark:border-gray-700'">
                <span v-for="tag in model" :key="tag" class="inline-flex items-center gap-1 rounded bg-surface px-1.5 py-0.5 text-sm">
                    {{ tag }}
                    <button type="button" class="text-muted hover:text-ink" :aria-label="t('Remove')" @click="remove(tag)"><X class="size-3" /></button>
                </span>
                <input
                    :id="id"
                    v-model="text"
                    type="text"
                    autocomplete="off"
                    :placeholder="model.length ? '' : placeholder"
                    class="h-7 min-w-24 flex-1 bg-transparent text-sm text-gray-800 outline-none dark:text-white/90"
                    @focus="open = true"
                    @input="open = true"
                    @blur="onBlur"
                    @keydown="onKeydown"
                />
            </div>
            <ul v-if="open && matching.length" class="absolute z-20 mt-1 max-h-56 w-full overflow-auto rounded-[10px] border border-line bg-card p-1 shadow-md">
                <li v-for="suggestion in matching" :key="suggestion">
                    <button type="button" class="w-full rounded px-2 py-1.5 text-start text-sm hover:bg-surface" @mousedown.prevent="add(suggestion)">{{ suggestion }}</button>
                </li>
            </ul>
        </div>
    </Field>
</template>
