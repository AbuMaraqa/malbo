<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { t } from '@/lib/i18n';
import { Trash2 } from '@/lib/icons';

import LineQuantity from './LineQuantity.vue';
import type { CartLine } from './types';

/** One cart line: image, name and options, unit price, quantity, line total and remove. */
defineProps<{ line: CartLine; busy: boolean }>();

const emit = defineEmits<{ quantity: [quantity: number]; remove: [] }>();
</script>

<template>
    <li class="flex gap-4 py-5" :class="{ 'opacity-70': !line.available }">
        <div class="size-24 shrink-0 overflow-hidden rounded-md bg-(--sand)">
            <img v-if="line.image" :src="line.image" :alt="line.name" class="size-full object-cover" loading="lazy" />
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link v-if="line.url" :href="line.url" class="font-bold hover:underline">{{ line.name }}</Link>
                    <p v-else class="font-bold">{{ line.name }}</p>
                    <p v-if="line.options.length" class="mt-1 text-sm text-(--ink-soft)">
                        <span v-for="(option, index) in line.options" :key="option.name">{{ index ? ' · ' : '' }}{{ option.name }}{{ option.value ? `: ${option.value}` : '' }}</span>
                    </p>
                    <p class="mt-1 text-sm text-muted">{{ t('Unit price') }}: {{ line.price.label }}</p>
                </div>
                <button
                    type="button"
                    class="grid size-10 shrink-0 cursor-pointer place-items-center rounded-md text-muted hover:bg-(--sand-light) hover:text-ink disabled:opacity-40"
                    :aria-label="t('Remove :name', { name: line.name })"
                    :disabled="busy"
                    @click="emit('remove')"
                >
                    <Trash2 class="size-5" aria-hidden="true" />
                </button>
            </div>
            <p v-if="line.problem" class="mt-2 text-sm font-bold text-(--danger)">{{ line.problem }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <LineQuantity :quantity="line.quantity" :max="Math.max(line.max_quantity, line.quantity)" :name="line.name" :disabled="busy" @change="emit('quantity', $event)" />
                <p class="font-bold">{{ line.total_label }}</p>
            </div>
        </div>
    </li>
</template>
