<script setup lang="ts">
import { computed } from 'vue';

import { chip } from './styles';
import type { ProductAttribute } from './types';

/** One attribute's values: color swatches for a color attribute, chips for the rest. */
const props = defineProps<{ attribute: ProductAttribute }>();

const model = defineModel<number | undefined>();

const chosen = computed(() => props.attribute.values.find((value) => value.id === model.value));
const groupName = computed(() => `attribute-${props.attribute.id}`);
</script>

<template>
    <fieldset>
        <legend class="mb-3 font-bold">
            {{ attribute.name }}
            <span v-if="chosen" class="font-normal text-muted">: {{ chosen.name }}</span>
        </legend>
        <div class="flex flex-wrap gap-2">
            <label v-for="value in attribute.values" :key="value.id" class="cursor-pointer">
                <input v-model="model" type="radio" class="peer sr-only" :name="groupName" :value="value.id" />
                <span
                    v-if="attribute.swatch && value.color"
                    class="block size-11 rounded-full border border-line p-1 peer-checked:border-2 peer-checked:border-ink peer-focus-visible:outline-2 peer-focus-visible:outline-store"
                >
                    <span class="block size-full rounded-full" :style="{ backgroundColor: value.color }"></span>
                    <span class="sr-only">{{ value.name }}</span>
                </span>
                <span v-else :class="chip" class="peer-checked:border-ink peer-checked:font-bold peer-focus-visible:outline-2 peer-focus-visible:outline-store">{{ value.name }}</span>
            </label>
        </div>
    </fieldset>
</template>
