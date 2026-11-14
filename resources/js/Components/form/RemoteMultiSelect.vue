<script setup lang="ts">
/**
 * Many values from a list too long to send with the page: the server answers each search.
 *
 *   <RemoteMultiSelect v-model="form.products" :url="route('products.lookup')" :initial="chosen" :label="t('Products')" />
 *
 * - `url` answers `?q=term` with a list of `{ value, label }`.
 * - `initial` holds the options already chosen, so saved values show their labels.
 * - The values keep the order they were picked in.
 */
import { computed, onBeforeUnmount, onMounted, shallowRef } from 'vue';

import MultiSelect from './MultiSelect.vue';
import type { Option } from './SelectInput.vue';

const props = withDefaults(
    defineProps<{
        url: string;
        initial?: Option[];
        label?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
    }>(),
    { initial: () => [] },
);

const model = defineModel<(string | number)[]>({ default: () => [] });

/** Every option seen so far, so a chosen value keeps its label after another search. */
const known = new Map<string, Option>(props.initial.map((option) => [String(option.value), option]));
const found = shallowRef<Option[]>([]);

const options = computed<Option[]>(() => {
    const chosen = model.value.map((value) => known.get(String(value))).filter((option): option is Option => option !== undefined);
    const chosenKeys = new Set(model.value.map(String));

    return [...chosen, ...found.value.filter((option) => !chosenKeys.has(String(option.value)))];
});

let timer: ReturnType<typeof setTimeout> | undefined;
let request: AbortController | undefined;

async function load(term: string): Promise<void> {
    request?.abort();
    request = new AbortController();

    try {
        const separator = props.url.includes('?') ? '&' : '?';
        const response = await fetch(`${props.url}${separator}q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: request.signal });

        if (!response.ok) return;

        const list = (await response.json()) as Option[];
        list.forEach((option) => known.set(String(option.value), option));
        found.value = list;
    } catch {
        // A newer search replaced this one, or the network failed: the list stays as it is.
    }
}

function search(term: string): void {
    clearTimeout(timer);
    timer = setTimeout(() => load(term), 250);
}

function choose(value: unknown): void {
    model.value = Array.isArray(value) ? (value as (string | number)[]) : [];
}

onMounted(() => load(''));
onBeforeUnmount(() => {
    clearTimeout(timer);
    request?.abort();
});
</script>

<template>
    <MultiSelect :model-value="model" remote :options="options" :label="label" :error="error" :hint="hint" :required="required" :placeholder="placeholder" @search="search" @update:model-value="choose" />
</template>
