<script setup lang="ts" generic="T extends Option">
/**
 * One value from a list too long to send with the page: the server answers each search.
 *
 *   <RemoteSelect v-model="form.user_id" :url="route('orders.lookup', { type: 'customers' })" :initial="selected" :label="t('Customer')" />
 *
 * - `url` answers `?q=term` with a list of `{ value, label }`; extra fields are kept.
 * - `initial` holds the options already chosen, so a saved value shows its label.
 * - `picked` fires with the whole option when the user chooses one.
 */
import { onBeforeUnmount, onMounted, shallowRef } from 'vue';

import MultiSelect from './MultiSelect.vue';
import type { Option } from './SelectInput.vue';

const props = withDefaults(
    defineProps<{
        url: string;
        initial?: T[];
        label?: string;
        error?: string;
        hint?: string;
        required?: boolean;
        placeholder?: string;
    }>(),
    { initial: () => [] },
);

const emit = defineEmits<{ picked: [option: T] }>();

const model = defineModel<string | number | null>();
const options = shallowRef<T[]>([...props.initial]);

let timer: ReturnType<typeof setTimeout> | undefined;
let request: AbortController | undefined;

async function load(term: string): Promise<void> {
    request?.abort();
    request = new AbortController();

    try {
        const separator = props.url.includes('?') ? '&' : '?';
        const response = await fetch(`${props.url}${separator}q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: request.signal });

        if (!response.ok) return;

        const found = (await response.json()) as T[];
        const chosen = options.value.filter((option) => String(option.value) === String(model.value));

        options.value = [...chosen, ...found.filter((option) => String(option.value) !== String(model.value))];
    } catch {
        // A newer search replaced this one, or the network failed: the list stays as it is.
    }
}

function search(term: string): void {
    clearTimeout(timer);
    timer = setTimeout(() => load(term), 250);
}

function choose(value: unknown): void {
    model.value = value as string | number | null;

    const option = options.value.find((item) => String(item.value) === String(value));
    if (option) emit('picked', option);
}

onMounted(() => load(''));
onBeforeUnmount(() => {
    clearTimeout(timer);
    request?.abort();
});
</script>

<template>
    <MultiSelect :model-value="model" single remote :options="options" :label="label" :error="error" :hint="hint" :required="required" :placeholder="placeholder" @search="search" @update:model-value="choose" />
</template>
