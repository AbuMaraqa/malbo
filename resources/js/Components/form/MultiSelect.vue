<script setup lang="ts">
import { Check, ChevronDown, X } from '@/lib/icons';
import { ComboboxAnchor, ComboboxContent, ComboboxEmpty, ComboboxInput, ComboboxItem, ComboboxItemIndicator, ComboboxPortal, ComboboxRoot, ComboboxTrigger, ComboboxViewport } from 'reka-ui';
import { computed, ref, useId, watch } from 'vue';

import { t } from '@/lib/i18n';

import Field from './Field.vue';
import type { Option } from './SelectInput.vue';

const props = defineProps<{
    label?: string;
    error?: string;
    hint?: string;
    required?: boolean;
    options: Option[];
    placeholder?: string;
    /** Single choice with a search box instead of many. */
    single?: boolean;
    /** The server does the searching (see RemoteSelect): `options` are shown as they come, `search` fires. */
    remote?: boolean;
}>();

const emit = defineEmits<{ search: [term: string] }>();

const model = defineModel<(string | number)[] | string | number | null>();
const id = useId();
const search = ref('');

const selected = computed<(string | number)[]>(() => {
    if (Array.isArray(model.value)) return model.value;
    return model.value === null || model.value === undefined || model.value === '' ? [] : [model.value];
});

watch(search, (term) => {
    if (props.remote) emit('search', term);
});

const visible = computed(() => {
    if (props.remote) return props.options;

    const term = search.value.trim().toLowerCase();
    return term ? props.options.filter((option) => option.label.toLowerCase().includes(term)) : props.options;
});

const labelOf = (value: string | number): string =>
    props.options.find((option) => String(option.value) === String(value))?.label ?? String(value);

function remove(value: string | number): void {
    model.value = props.single ? null : selected.value.filter((item) => item !== value);
}
</script>

<template>
    <Field :id="id" :label="label" :error="error" :hint="hint" :required="required">
        <ComboboxRoot v-model="model as never" :multiple="!single" ignore-filter class="relative" @update:open="search = ''">
            <ComboboxAnchor
                class="flex min-h-9 w-full flex-wrap items-center gap-1 rounded-control border bg-card px-2 py-1 focus-within:border-brand"
                :class="error ? 'border-danger' : 'border-line'"
            >
                <span v-for="value in selected" :key="value" class="inline-flex items-center gap-1 rounded bg-surface px-1.5 py-0.5 text-sm">
                    {{ labelOf(value) }}
                    <button type="button" class="text-muted hover:text-ink" :aria-label="t('Remove')" @click.stop="remove(value)"><X class="size-3" /></button>
                </span>
                <ComboboxInput
                    :id="id"
                    v-model="search"
                    :placeholder="selected.length ? '' : (placeholder ?? t('Search...'))"
                    :display-value="() => search"
                    class="h-6 min-w-16 flex-1 bg-transparent text-sm outline-none"
                />
                <ComboboxTrigger class="text-muted" :aria-label="t('Open')"><ChevronDown class="size-4" /></ComboboxTrigger>
            </ComboboxAnchor>
            <ComboboxPortal>
                <ComboboxContent position="popper" :side-offset="4" class="z-[100] max-h-64 w-[var(--reka-combobox-trigger-width)] overflow-hidden rounded-control border border-line bg-card shadow-md">
                    <ComboboxViewport class="p-1">
                        <ComboboxEmpty class="px-2 py-3 text-center text-sm text-muted">{{ t('No results') }}</ComboboxEmpty>
                        <ComboboxItem
                            v-for="option in visible"
                            :key="option.value"
                            :value="option.value"
                            class="flex cursor-pointer items-center justify-between gap-2 rounded px-2 py-1.5 text-sm data-[highlighted]:bg-surface"
                        >
                            {{ option.label }}
                            <ComboboxItemIndicator><Check class="size-4 text-brand" /></ComboboxItemIndicator>
                        </ComboboxItem>
                    </ComboboxViewport>
                </ComboboxContent>
            </ComboboxPortal>
        </ComboboxRoot>
    </Field>
</template>
