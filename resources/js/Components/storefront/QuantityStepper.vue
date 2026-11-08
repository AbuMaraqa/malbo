<script setup lang="ts">
import { t } from '@/lib/i18n';

/** The buyer's quantity. The server caps it by the order limit and the stock. */
defineProps<{ max: number }>();

const model = defineModel<number>({ required: true });

function set(event: Event): void {
    const value = parseInt((event.target as HTMLInputElement).value, 10);

    model.value = Number.isNaN(value) ? 1 : value;
}
</script>

<template>
    <div>
        <span id="quantity-label" class="mb-3 block font-bold">{{ t('Quantity') }}</span>
        <div class="inline-flex items-center rounded-md border border-(--ink-30) bg-white" role="group" aria-labelledby="quantity-label">
            <button type="button" class="grid size-11 cursor-pointer place-items-center text-xl disabled:cursor-not-allowed disabled:opacity-40" :aria-label="t('Decrease quantity')" :disabled="model <= 1" @click="model -= 1">−</button>
            <input
                :value="model"
                type="number"
                min="1"
                :max="max"
                inputmode="numeric"
                aria-labelledby="quantity-label"
                class="h-11 w-14 border-x border-(--ink-30) text-center [appearance:textfield]"
                @change="set"
            />
            <button type="button" class="grid size-11 cursor-pointer place-items-center text-xl disabled:cursor-not-allowed disabled:opacity-40" :aria-label="t('Increase quantity')" :disabled="model >= max" @click="model += 1">+</button>
        </div>
    </div>
</template>
