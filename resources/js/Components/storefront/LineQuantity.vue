<script setup lang="ts">
import { t } from '@/lib/i18n';

/** A cart line's quantity: − and +, or a typed number. Emits the new quantity; the server checks it. */
const props = defineProps<{ quantity: number; max: number; name: string; disabled?: boolean }>();

const emit = defineEmits<{ change: [quantity: number] }>();

function typed(event: Event): void {
    const value = parseInt((event.target as HTMLInputElement).value, 10);

    if (!Number.isNaN(value) && value >= 1 && value !== props.quantity) emit('change', value);
}
</script>

<template>
    <div class="inline-flex items-center rounded-md border border-(--ink-30) bg-white" role="group" :aria-label="t('Quantity of :name', { name })">
        <button
            type="button"
            class="grid size-10 cursor-pointer place-items-center text-lg disabled:cursor-not-allowed disabled:opacity-40"
            :aria-label="t('Decrease quantity')"
            :disabled="disabled || quantity <= 1"
            @click="emit('change', quantity - 1)"
        >
            −
        </button>
        <input
            :value="quantity"
            type="number"
            min="1"
            :max="max"
            inputmode="numeric"
            :disabled="disabled"
            :aria-label="t('Quantity')"
            class="h-10 w-12 border-x border-(--ink-30) text-center [appearance:textfield]"
            @change="typed"
        />
        <button
            type="button"
            class="grid size-10 cursor-pointer place-items-center text-lg disabled:cursor-not-allowed disabled:opacity-40"
            :aria-label="t('Increase quantity')"
            :disabled="disabled || quantity >= max"
            @click="emit('change', quantity + 1)"
        >
            +
        </button>
    </div>
</template>
