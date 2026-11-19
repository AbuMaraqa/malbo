<script setup lang="ts">
import { ref, useId, watch } from 'vue';

import { t } from '@/lib/i18n';

import { btnOutline } from './styles';

/**
 * The price filter: from and to on the price the cards show. The bounds of what the other filters
 * leave are the placeholders. Applied with the button or Enter.
 */
const props = defineProps<{ min: number | null; max: number | null; bounds: { min: number | null; max: number | null } }>();

const emit = defineEmits<{ apply: [min: number | null, max: number | null] }>();

// A number field's v-model gives a number once something is typed, and '' when it is emptied.
const from = ref<string | number>(props.min ?? '');
const to = ref<string | number>(props.max ?? '');
const id = useId();

watch(() => [props.min, props.max], ([min, max]) => {
    from.value = min ?? '';
    to.value = max ?? '';
});

/** The one place a typed bound becomes a filter: none when empty, not a number or below 0. */
function number(value: string | number): number | null {
    const text = String(value).trim();
    const parsed = Number.parseFloat(text);

    return text === '' || Number.isNaN(parsed) || parsed < 0 ? null : parsed;
}

function apply(): void {
    emit('apply', number(from.value), number(to.value));
}

const input = 'h-10 w-full min-w-0 rounded-md border border-line bg-(--shell) px-3 text-sm outline-none focus:border-store';
</script>

<template>
    <fieldset>
        <legend class="mb-3 text-[0.9375rem] font-bold">{{ t('Price') }}</legend>
        <form class="flex items-end gap-2" @submit.prevent="apply">
            <label :for="`${id}-from`" class="flex-1">
                <span class="mb-1 block text-xs text-muted">{{ t('From') }}</span>
                <input :id="`${id}-from`" v-model="from" type="number" inputmode="decimal" min="0" step="any" :placeholder="bounds.min === null ? '' : String(Math.floor(bounds.min))" :class="input" dir="ltr" />
            </label>
            <label :for="`${id}-to`" class="flex-1">
                <span class="mb-1 block text-xs text-muted">{{ t('To') }}</span>
                <input :id="`${id}-to`" v-model="to" type="number" inputmode="decimal" min="0" step="any" :placeholder="bounds.max === null ? '' : String(Math.ceil(bounds.max))" :class="input" dir="ltr" />
            </label>
            <button type="submit" :class="btnOutline" class="min-h-10! px-4! text-sm">{{ t('Apply') }}</button>
        </form>
    </fieldset>
</template>
